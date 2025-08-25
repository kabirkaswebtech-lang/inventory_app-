<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use Illuminate\Support\Facades\Http;
use App\Models\Inverntry;

class ExcelController extends Controller
{

    protected $shopifyEndpoint;
    private $accessToken;
    protected $shopifyDomain;
    public function downloadFromSharePoint()
{

    set_time_limit(0);
    ini_set('memory_limit', '-1');
      
   
    $sharepointUrl = 'https://cuestix-my.sharepoint.com/:x:/p/andrew/ERXI0qVZDtVLgxLj42vRBFwBfnGhfeFk247aX2FuwgDTHQ?rtime=jRdFdJHY3Ug&download=1';

    try {
        // 1. Download Excel file
        $response = Http::get($sharepointUrl);
        if (!$response->ok()) {
            return response()->json(['error' => 'Failed to download file from SharePoint'], 500);
        }

        // 2. Save as temporary file
        $tempPath = storage_path('app/temp_file.xlsx');
        file_put_contents($tempPath, $response->body());

        // 3. Read spreadsheet
        $spreadsheet = IOFactory::load($tempPath);
        $rows = $spreadsheet->getActiveSheet()->toArray();

        // Always clean up temp file
        unlink($tempPath);

        if (empty($rows) || count($rows) < 2) {
            return response()->json(['error' => 'No data found in spreadsheet'], 400);
        }

        // 4. Map headers
        $header = array_map('strtolower', $rows[0]);
        $skuIndex       = array_search('sku', $header);
        $retailIndex    = array_search('retail', $header);
        $mapIndex       = array_search('map', $header);
        $wholesaleIndex = array_search('wholesale', $header);
        $updateIndex = array_search('update', $header);

        if ($skuIndex === false || $wholesaleIndex === false) {
            return response()->json(['error' => 'Required columns missing in spreadsheet'], 400);
        }

        $updatedProducts = [];

        // 5. Loop through rows
        foreach (array_slice($rows, 1) as $row) {
            $rawSku = $row[$skuIndex] ?? null;
            if (empty($rawSku)) continue;

            // Format SKU
            $sku = 'PSS-' . str_replace(' ', '', trim($rawSku));

            // Get values
            $retail    = $row[$retailIndex] ?? null;
            $map       = $row[$mapIndex] ?? null;
            $wholesale = $row[$wholesaleIndex] ?? null;
            $update = $row[$updateIndex] ?? null;

            // Calculate MSRP if missing
            if (empty($map) && empty($retail) && !empty($wholesale)) {
                $retail = round($wholesale * 1.4, 2);
            }

            // Determine final price (MAP > MSRP)
            // $finalPrice = !empty($map) ? $map : $retail;
            $finalPrice = !empty($retail) ? $retail : (!empty($map) ? $map : '');
            if (!empty($finalPrice)) {
                // Call Shopify update
                //$shopifyResponse = $this->updateShopifyPrice($sku, $finalPrice);

                $updatedProducts[] = [
                    'sku'          => $sku,
                    'final_price'  => $finalPrice,
                    'map'          => $map,
                    'retail'       => $retail,
                    'wholesale'    => $wholesale,
                    'update' => $update,
                    //'shopify_result' => $shopifyResponse
                ];
            }
        }

        return response()->json([
            'status'  => 'success',
            'data' => $updatedProducts
        ]);

    } catch (\Exception $e) {
        return response()->json(['error' => 'Error: ' . $e->getMessage()], 500);
    }
}
  
   public function __construct()
    {
        $this->shopifyDomain = "brmidz-iw.myshopify.com";
        $this->shopifyEndpoint = 'https://brmidz-iw.myshopify.com/admin/api/2024-10/graphql.json'; // Replace with your store
        $this->accessToken = env('SHOPIFY_ACCESS_TOKEN');
    }

    /**
     * Update prices and compare prices by SKU from input data.
     * Expected input JSON structure:
     * {
     *   "data": [
     *     { "sku": "SKU123", "price": "29.99", "compare_price": "39.99" },
     *     { "sku": "SKU456", "price": "15.00" }
     *   ]
     * }
     */



  public function batchUpdateShopifyPrices(Request $request)
{
    set_time_limit(0);
    ini_set('memory_limit', '-1');
    
    $payload = $request->input('data', []);
    if (empty($payload)) {
        return response()->json(['error' => 'No data provided'], 400);
    }

    $results = [];

    foreach ($payload as $item) {
        if (empty($item['sku']) || empty($item['price'])) {
            $results[] = [
                'sku' => $item['sku'] ?? null,
                'status' => 'failed',
                'message' => 'Missing sku or price',
            ];
            continue;
        }

        try {
            $updateResult = $this->updateShopifyPriceBySKU($item['sku'], $item['price'], $item['compare_price'] ?? null);


            $data = $updateResult->getData(true);

            if ($data['status'] === 'success') {
                $results[] = [
                    'sku' => $item['sku'],
                    'status' => 'success',
                    'message' => $data['message'],
                ];
            } else {
                $results[] = [
                    'sku' => $item['sku'],
                    'status' => 'failed',
                    'message' => $data['message'],
                ];
            }
        } catch (\Exception $e) {
            $results[] = [
                'sku' => $item['sku'],
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    return response()->json(['results' => $results]);
}

   public function updateShopifyPriceBySKU($skuToFindRaw, $newPrice, $comparePrice)
    {
      
      
        $skuToFind = trim($skuToFindRaw);

        // Step 1: Search variants by SKU (GraphQL)
        $graphqlUrl = "https://{$this->shopifyDomain}/admin/api/2024-04/graphql.json";

        $searchQuery = <<<'GRAPHQL'
            query searchBySku($q: String!) {
            productVariants(first: 1, query: $q) {
                edges {
                node {
                    id
                    sku
                    price
                    compareAtPrice
                    product {
                    id
                    title
                    }
                }
                }
            }
            }
            GRAPHQL;

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->accessToken,
            'Content-Type' => 'application/json',
        ])->post($graphqlUrl, [
            'query' => $searchQuery,
            'variables' => [
                'q' => "sku:{$skuToFind}"
            ],
        ]);

        $searchJson = $response->json();

        if (!isset($searchJson['data']['productVariants']['edges']) || count($searchJson['data']['productVariants']['edges']) === 0) {
            return response()->json(['status' => 'error','error' => "SKU not found: {$skuToFind}"], 404);
        }

        $fullId = $searchJson['data']['productVariants']['edges'][0]['node']['id']; // gid://shopify/ProductVariant/46665460383956
        $numericId = last(explode('/', $fullId));

        return $this->updateVariantPrice($numericId, $newPrice, $comparePrice);
    }

    private function updateVariantPrice($variantId, $newPrice, $comparePrice)
    {
       
        $url = "https://{$this->shopifyDomain}/admin/api/2024-04/variants/{$variantId}.json";

        $payload = [
            'variant' => [
                'id' => (int) $variantId,
                'price' => $newPrice,
                'compare_at_price' => $comparePrice,
            ],
        ];

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->accessToken,
            'Content-Type' => 'application/json',
        ])->put($url, $payload);

        if ($response->successful()) {
            return response()->json(['message' => 'Variant price updated successfully.','status' => 'success']);
        } else {
            return response()->json(['status' => 'error','error' => 'Failed to update variant price.', 'details' => $response->json()], $response->status());
        }
    }


        public function getAllProducts($skuToFindRaw, $newQuantity)
          {
          
          set_time_limit(0);
          ini_set('memory_limit', '-1');

            $skuToFind = trim($skuToFindRaw);

            // Step 1: Search variants by SKU (GraphQL)
            $graphqlUrl = "https://{$this->shopifyDomain}/admin/api/2024-04/graphql.json";

            $searchQuery = <<<'GRAPHQL'
                query searchBySku($q: String!) {
                    productVariants(first: 1, query: $q) {
                        edges {
                            node {
                                id
                                sku
                                inventoryQuantity
                                inventoryItem {
                                    id
                                }
                            }
                        }
                    }
                }
            GRAPHQL;

            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken,
                'Content-Type' => 'application/json',
            ])->post($graphqlUrl, [
                'query' => $searchQuery,
                'variables' => [
                    'q' => "sku:{$skuToFind}"
                ],
            ]);

            $searchJson = $response->json();
            
          
            if (!isset($searchJson['data']['productVariants']['edges']) || count($searchJson['data']['productVariants']['edges']) === 0) {
                return response()->json(['status' => 'error','error' => "SKU not found: {$skuToFind}"], 404);
            }

            $variantNode = $searchJson['data']['productVariants']['edges'][0]['node'];
            $inventoryItemId = $variantNode['inventoryItem']['id'];

            // Extract numeric ID from gid://shopify/InventoryItem/xxxx
            $inventoryItemNumericId = last(explode('/', $inventoryItemId));

            // Step 2: Get locationId via REST API
            $locationResponse = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken,
                'Content-Type' => 'application/json',
            ])->get("https://{$this->shopifyDomain}/admin/api/2024-04/locations.json");

            $locationData = $locationResponse->json();
            if (empty($locationData['locations'])) {
                return response()->json(['status' => 'error','error' => 'No locations found'], 404);
            }

            $locationId = $locationData['locations'][0]['id']; // First location

            // Step 3: Update inventory quantity using REST API
            $updateResponse = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken,
                'Content-Type' => 'application/json',
            ])->post("https://{$this->shopifyDomain}/admin/api/2024-04/inventory_levels/set.json", [
                'location_id' => $locationId,
                'inventory_item_id' => $inventoryItemNumericId,
                'available' => (int) $newQuantity
            ]);

            return $updateResponse->json();
        }

  
  
   public function downloadFromDropBox()
{
    set_time_limit(0);
    ini_set('memory_limit', '-1');

    // Get direct download link from Dropbox
    $dropboxUrl = 'https://www.dropbox.com/scl/fi/jm2jy8kax3ap78gxuwzi4/Internet-Dealer-Inventory-Feed.CSV?rlkey=vitgth7jtonrqqk9pg074wq2b&dl=1';

    try {
        // 1. Download CSV file
        $response = Http::get($dropboxUrl);
        if (!$response->ok()) {
            return response()->json(['error' => 'Failed to download file from Dropbox'], 500);
        }

        // 2. Save as temporary CSV file
        $tempPath = storage_path('app/temp_file.csv');
        file_put_contents($tempPath, $response->body());

        // 3. Read CSV using CSV Reader (avoids XML parser)
        $reader = new Csv();
        $reader->setDelimiter(',');
        $reader->setEnclosure('"');
        $reader->setSheetIndex(0);
        $spreadsheet = $reader->load($tempPath);

        $rows = $spreadsheet->getActiveSheet()->toArray();

        // Always clean up
        unlink($tempPath);

        if (empty($rows) || count($rows) < 2) {
            return response()->json(['error' => 'No data found in spreadsheet'], 400);
        }

        // 4. Map headers
        $header = array_map('strtolower', $rows[0]);

        $itemNumberIndex = array_search('item_nmbr', $header);
        $availableIndex  = array_search('available', $header);
        $discontIndex    = array_search('discont', $header);
        $upcIndex        = array_search('upc_code', $header);

        if ($itemNumberIndex === false) {
            return response()->json(['error' => 'Required column Item_Nmbr missing in spreadsheet'], 400);
        }

        $updatedProducts = [];

        // 5. Loop through rows
        foreach (array_slice($rows, 1) as $row) {
            $itemNumber = $row[$itemNumberIndex] ?? null;
            if (empty($itemNumber)) continue;

            $sku       = 'PSS-' . str_replace(' ', '', trim($itemNumber));
            $available = $row[$availableIndex] ?? null;
            $discont   = $row[$discontIndex] ?? null;
            $upcCode   = $row[$upcIndex] ?? null;

            $updatedProducts[] = [
                'sku'       => $sku,
                'available' => $available,
                'discont'   => $discont,
                'upc_code'  => $upcCode
            ];
        }
      
       

        return response()->json([
            'status' => 'success',
            'data'   => $updatedProducts
        ]);

    } catch (\Exception $e) {
        return response()->json(['error' => 'Error: ' . $e->getMessage()], 500);
    }
}



public function updateInventoryFromAjax(Request $request)
{
  
    set_time_limit(0);
    ini_set('memory_limit', '-1');
  
    $data = $request->input('data', []);

    if (empty($data)) {
        return response()->json(['status' => 'error', 'message' => 'No data received'], 400);
    }

    $results = [];

    foreach ($data as $row) {
        try {
            // row[sku, available, Upc_code] based on your JS
            $sku = $row['sku'];
            $available = $row['available'];

            // Call your existing method to update inventory
            $results[] = $this->getAllProducts($sku, $available);

        } catch (\Exception $e) {
            $results[] = [
                'status' => 'error',
                'sku' => $sku,
                'message' => $e->getMessage()
            ];
        }
    }

    return response()->json(['status' => 'success', 'results' => $results]);
}


public function updateInventoryFromDropBox()
{

    // Dropbox direct download link
    $dropboxUrl = 'https://www.dropbox.com/scl/fi/jm2jy8kax3ap78gxuwzi4/Internet-Dealer-Inventory-Feed.CSV?rlkey=vitgth7jtonrqqk9pg074wq2b&dl=1';

    try {
        // 1. Download CSV file
        $response = Http::get($dropboxUrl);
        if (!$response->ok()) {
            return response()->json(['error' => 'Failed to download file from Dropbox'], 500);
        }

        // 2. Save as temporary CSV file
        $tempPath = storage_path('app/temp_file.csv');
        file_put_contents($tempPath, $response->body());

        // 3. Read CSV with PhpSpreadsheet CSV Reader
        $reader = new Csv();
        $reader->setDelimiter(',');
        $reader->setEnclosure('"');
        $reader->setSheetIndex(0);
        $spreadsheet = $reader->load($tempPath);

        $rows = $spreadsheet->getActiveSheet()->toArray();

        // Clean up temp file
        unlink($tempPath);

        if (empty($rows) || count($rows) < 2) {
            return response()->json(['error' => 'No data found in spreadsheet'], 400);
        }

        // 4. Map headers
        $header          = array_map('strtolower', $rows[0]);
        $itemNumberIndex = array_search('item_nmbr', $header);
        $availableIndex  = array_search('available', $header);
        $discontIndex    = array_search('discont', $header);
        $upcIndex        = array_search('upc_code', $header);

        if ($itemNumberIndex === false) {
            return response()->json(['error' => 'Required column Item_Nmbr missing in spreadsheet'], 400);
        }

        $results = [];
        $count   = 0;
        $total   = count($rows) - 1; // excluding header row

        // 5. Loop rows + update inventory
        foreach (array_slice($rows, 1) as $row) {
            try {
                $itemNumber = $row[$itemNumberIndex] ?? null;
                if (empty($itemNumber)) continue;

                $sku       = 'PSS-' . str_replace(' ', '', trim($itemNumber));
                $available = $row[$availableIndex] ?? null;
                $discont   = $row[$discontIndex] ?? null;
                $upcCode   = $row[$upcIndex] ?? null;

                // Call Shopify inventory update (or your own method)
                $updateResult = $this->getAllProducts($sku, $available);

                $results[] = [
                    'sku'       => $sku,
                    'available' => $available,
                    'discont'   => $discont,
                    'upc_code'  => $upcCode,
                    'status'    => 'success',
                    'update'    => $updateResult
                ];

                $count++;

                // Add delay except after last item
                if ($count < $total) {
                    if ($count % 10 === 0) {
                        sleep(10); // every 100th request → 3 sec pause
                    } else {
                        sleep(2); // otherwise → 2 sec pause
                    }
                }

            } catch (\Exception $e) {
                $results[] = [
                    'sku'     => $row[$itemNumberIndex] ?? 'unknown',
                    'status'  => 'error',
                    'message' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'status'  => 'success',
            'updated' => count($results),
            'results' => $results
        ]);

    } catch (\Exception $e) {
        return response()->json(['error' => 'Error: ' . $e->getMessage()], 500);
    }
}


public function updateFromSharePointset()
{
   
    $sharepointUrl = 'https://cuestix-my.sharepoint.com/:x:/p/andrew/ERXI0qVZDtVLgxLj42vRBFwBfnGhfeFk247aX2FuwgDTHQ?rtime=jRdFdJHY3Ug&download=1';

    try {
        // 1. Download Excel file
        $response = Http::get($sharepointUrl);
        if (!$response->ok()) {
            return response()->json(['error' => 'Failed to download file from SharePoint'], 500);
        }

        // 2. Save as temporary file
        $tempPath = storage_path('app/temp_file.xlsx');
        file_put_contents($tempPath, $response->body());

        // 3. Read spreadsheet
        $spreadsheet = IOFactory::load($tempPath);
        $rows = $spreadsheet->getActiveSheet()->toArray();

        // Clean up temp file
        unlink($tempPath);

        if (empty($rows) || count($rows) < 2) {
            return response()->json(['error' => 'No data found in spreadsheet'], 400);
        }

        // 4. Map headers
        $header         = array_map('strtolower', $rows[0]);
        $skuIndex       = array_search('sku', $header);
        $retailIndex    = array_search('retail', $header);
        $mapIndex       = array_search('map', $header);
        $wholesaleIndex = array_search('wholesale', $header);
        $updateIndex    = array_search('update', $header);

        if ($skuIndex === false || $wholesaleIndex === false) {
            return response()->json(['error' => 'Required columns missing in spreadsheet'], 400);
        }

        $data = [];

        // 5. Build dataset
        foreach (array_slice($rows, 1) as $row) {
            $rawSku = $row[$skuIndex] ?? null;
            if (empty($rawSku)) continue;

            $sku       = 'PSS-' . str_replace(' ', '', trim($rawSku));
            $retail    = $row[$retailIndex] ?? null;
            $map       = $row[$mapIndex] ?? null;
            $wholesale = $row[$wholesaleIndex] ?? null;
            $update    = $row[$updateIndex] ?? null;

            // Calculate MSRP if missing
            if (empty($map) && empty($retail) && !empty($wholesale)) {
                $retail = round($wholesale * 1.4, 2);
            }

            $finalPrice = !empty($retail) ? $retail : (!empty($map) ? $map : '');

            $data[] = [
                'sku'        => $sku,
                'map'        => $map,
                'retail'     => $retail,
                'wholesale'  => $wholesale,
                'update'     => $update,
                'finalPrice' => $finalPrice,
            ];
        }

        // 6. Transform into Shopify payload (only rows with update flag)
        $payload = collect($data)
            ->filter(function ($row) {
                return !empty($row['update']);
            })
            ->map(function ($row) {
                return [
                    'sku'           => $row['sku'],
                    'price'         => !empty($row['map']) ? $row['map'] : $row['retail'],
                    'compare_price' => $row['retail'],
                ];
            })
            ->filter(function ($item) {
                return !empty($item['sku']) && !empty($item['price']);
            })
            ->values();

        // 7. Update Shopify with delays
        $results = [];
        $count   = 0;
        $total   = $payload->count();

        foreach ($payload as $item) {
            $results[] = $this->updateShopifyPriceBySKU(
                $item['sku'],
                $item['price'],
                $item['compare_price']
            );

            $count++;

            // Add delay except after last item
            if ($count < $total) {
                if ($count % 10 === 0) {
                    sleep(1); // every 100th request, wait 3 seconds
                } else {
                    sleep(1); // otherwise, wait 2 seconds
                }
            }
        }

        return response()->json([
            'total'   => $payload->count(),
            'results' => $results,
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Error: ' . $e->getMessage()], 500);
    }
}



}



