<!DOCTYPE html>
<html>
<head>
    <title>Excel from SharePoint (AJAX + DataTables)</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

    <style>
        /* Base */
        body {
            font-family: 'Inter', sans-serif;
            background: #f9fafb;
            padding: 40px 20px;
            color: #333;
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding:32px;
        }

        h2 {
            font-weight: 600;
            margin-bottom: 20px;
            color: #111827;
        }

        /* Button */
        #loadExcelBtn,#getProductsBtn {
            background-color: #2563eb;
            border: none;
            padding: 12px 28px;
            color: white;
            font-size: 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            box-shadow: 0 4px 10px rgb(37 99 235 / 0.3);
        }

        #loadExcelBtn:hover,#getProductsBtn:hover {
            background-color: #1e40af;
            box-shadow: 0 6px 15px rgb(30 64 175 / 0.4);
        }

        /* Loader container */
        #loader {
            display: none;
            margin-top: 20px;
            text-align: center;
        }
      
        #updateBtn,#InventoryBtn{
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
            border: none;
            color: white;
            padding: 10px 20px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: background 0.3s ease, transform 0.2s ease;
        }
        #updateBtn:hover,#InventoryBtn:hover {
            background: linear-gradient(135deg, #45A049, #256029);
            transform: translateY(-2px);
        }
        #updateBtn:active,#InventoryBtn:active {
            transform: translateY(0);
        }

        /* Table container */
        #tableContainer,#tabledropbox{
            margin-top: 30px;
            width: 100%;
            max-width: 1200px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 6px 15px rgba(0,0,0,0.1);
            overflow-x: auto;
            padding: 20px;
        }

        /* DataTables tweaks */
        table.dataTable thead th {
            background-color: #2563eb !important;
            color: white !important;
            font-weight: 600;
            text-align: left;
            padding: 12px 15px !important;
            border-bottom: none !important;
        }

        table.dataTable tbody td {
            padding: 12px 15px !important;
            border-bottom: 1px solid #e5e7eb !important;
            color: #374151;
        }

        table.dataTable tbody tr:hover {
            background-color: #eff6ff;
        }

        /* DataTables default elements */
        .dataTables_wrapper .dataTables_filter {
            margin-bottom: 10px;
            text-align: right;
        }

        .dataTables_wrapper .dataTables_length {
            margin-bottom: 10px;
        }

        .dataTables_wrapper .dataTables_info {
            margin-top: 10px;
            color: #6b7280;
        }

        .dataTables_wrapper .dataTables_paginate {
            margin-top: 15px;
            text-align: right;
        }

        /* Responsive scroll on smaller screens */
        @media (max-width: 600px) {
            #tableContainer {
                padding: 10px;
            }

            #loadExcelBtn,#getProductsBtn {
                width: 100%;
                margin: 5px;
                font-size: 18px;
            }
        }
    </style>
</head>
<body>

<h2>Share Point Excel</h2>
<p id="synmessege">Please wait — synchronization is in progress. Do not close the application.</p>
<div class="btn-flex">
<button id="loadExcelBtn">Pool Shark Price</button>
  <button id="getProductsBtn">Inventory Update</button>
</div>

<div id="loader">
    <svg width="40" height="40" viewBox="0 0 50 50" >
        <circle cx="25" cy="25" r="20" fill="none" stroke="#2563eb" stroke-width="5" stroke-linecap="round" stroke-dasharray="31.415, 31.415" transform="rotate(0 25 25)">
            <animateTransform attributeName="transform" attributeType="XML" type="rotate" from="0 25 25" to="360 25 25" dur="1s" repeatCount="indefinite"/>
        </circle>
    </svg>
</div>

<div id="tableContainer"></div>
<div id="tabledropbox"></div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script>
  $("#tableContainer").hide();
  $("#tabledropbox").hide();
  $("#synmessege").hide();
  
$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
});

$("#loadExcelBtn").on("click", function() {
    $("#loader").show();           // Show loader
    $("#tableContainer").hide().html(''); // Clear previous content
    $("#tabledropbox").hide().html(''); // Clear previous content

    $.ajax({
        url: "{{ secure_url('excel-from-sharepoint') }}",
        method: "POST",
        timeout: 0,
        success: function(response) {
            $("#loader").hide();  // Hide loader on success

            //console.log(response);

            if (response.error) {
                alert(response.error);
                return;
            }

            let data = response.data;

            if (!data || data.length === 0) {
                $("#tableContainer").html("<p style='text-align:center; color:#6b7280; font-style: italic;'>No data found.</p>");
                return;
            }

            let headerKeys = Object.keys(data[0]);
            let html = "<table id='sharepointTable' class='display' style='width:100%'><thead><tr>";
            headerKeys.forEach(key => {
                html += `<th>${key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' ')}</th>`;
            });
            html += "</tr></thead><tbody>";

            data.forEach(row => {
                html += "<tr>";
                headerKeys.forEach(key => {
                    html += `<td>${row[key] !== null && row[key] !== undefined ? row[key] : ''}</td>`;
                });
                html += "</tr>";
            });
            html += "</tbody></table>";

            // Add Update button after table
            html += `<div style="margin-top:15px; text-align:right;">
                        <button id="updateBtn" class="btn btn-primary">Update</button>
                    </div>`;

            $("#tableContainer").show().html(html);

            $('#sharepointTable').DataTable({
                paging: true,
                searching: true,
                ordering: true,
                info: true,
                lengthChange: true,
                pageLength: 10,
                autoWidth: false,
                language: {
                    search: "Filter records:"
                }
            });
        },
        error: function(xhr) {
            $("#loader").hide();  // Hide loader on error

           // console.log(xhr.responseText);
            alert("Error: " + xhr.responseText);
        }
    });
});

  

  
  
$("#getProductsBtn").on("click", function() {
    $("#loader").show();           // Show loader
    $("#tabledropbox").hide().html(''); // Clear previous content
    $("#tableContainer").hide().html(''); // Clear previous content

    $.ajax({
        url: "{{ secure_url('excel-from-dropbox') }}",
        method: "POST",
        timeout: 0,
        success: function(response) {
            $("#loader").hide();  // Hide loader on success

           // console.log(response);

            if (response.error) {
                alert(response.error);
                return;
            }

            let data = response.data;

            if (!data || data.length === 0) {
                $("#tabledropbox").html("<p style='text-align:center; color:#6b7280; font-style: italic;'>No data found.</p>");
                return;
            }

            let headerKeys = Object.keys(data[0]);
            let html = "<table id='dropboxTable' class='display' style='width:100%'><thead><tr>";
            headerKeys.forEach(key => {
                html += `<th>${key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' ')}</th>`;
            });
            html += "</tr></thead><tbody>";

            data.forEach(row => {
                html += "<tr>";
                headerKeys.forEach(key => {
                    html += `<td>${row[key] !== null && row[key] !== undefined ? row[key] : ''}</td>`;
                });
                html += "</tr>";
            });
            html += "</tbody></table>";

            // Add Update button after table
            html += `<div style="margin-top:15px; text-align:right;">
                        <button id="InventoryBtn" class="btn btn-primary">Update</button>
                    </div>`;

            $("#tabledropbox").show().html(html);

            $('#dropboxTable').DataTable({
                paging: true,
                searching: true,
                ordering: true,
                info: true,
                lengthChange: true,
                pageLength: 10,
                autoWidth: false,
                language: {
                    search: "Filter records:"
                }
            });
        },
        error: function(xhr) {
            $("#loader").hide();  // Hide loader on error

          //  console.log(xhr.responseText);
            alert("Error: " + xhr.responseText);
        }
    });
});
  
/*$(document).on('click', '#updateBtn', function () {
    $("#tableContainer").hide()
    var table = $('#sharepointTable').DataTable();
  
    $("#loader").show();  // Hide loader on error

    // Show all rows
   // table.page.len(-1).draw(false); // -1 = show all entries

    // Disable button while processing
    $("#updateBtn").prop("disabled", true).text("Updating...");

    // Get all rows at once
    var allData = table.rows().data().toArray();

    // Prepare payload
    var payload = allData
    .map(function (row) {
        // Check if row[5] exists and contains "NEW"
        if (row[5] && (row[5].toString().toLowerCase().includes("new") || row[5].toString().toLowerCase().includes("price"))) {
            return {
                sku: row[0],         // adjust indexes if needed
                price: row[4],
                compare_price: row[1]
            };
        }
        return null; // skip this row
    })
    .filter(function (item) {
        return item && item.sku !== '' && item.price !== '';
    });

    if (payload.length === 0) {
        alert("No valid data to update.");
        $("#updateBtn").prop("disabled", false).text("Update");
        return;
    }

    console.log('payload', payload);

    // Send in one request
    $.ajax({
        url: '{{ secure_url('shopify/prices-update') }}',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ data: payload }),
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function (response) {
            $("#loader").hide();  // Hide loader on error
            $("#tableContainer").show();
            console.log("Update response:", response);
            alert("All rows updated successfully!");
          
            $("#updateBtn").prop("disabled", false).text("Update");
        },
        error: function (xhr) {
            console.error("Error updating:", xhr.responseText);
            $("#updateBtn").prop("disabled", false).text("Update");
        }
    });
});*/

  
$(document).on('click', '#updateBtn', function () {
    $("#tableContainer").hide();
    $("#synmessege").show();
    var table = $('#sharepointTable').DataTable();
    $("#loader").show();

    $("#updateBtn").prop("disabled", true).text("Updating...");

    var allData = table.rows().data().toArray();

    var payload = allData
    .map(function (row) {
        return {
            sku: row[0],
            price: row[2] ? row[2] : row[1], // If MAP exists, use it, otherwise use retail
            compare_price: row[1] // Retail price
        };
    })
    .filter(function (item) {
        return item && item.sku !== '' && item.price !== '';
    });
    console.log(payload);
    return;
    if (payload.length === 0) {
        alert("No valid data to update.");
        $("#loader").hide();
        $("#tableContainer").show();
        $("#updateBtn").prop("disabled", false).text("Update");
        return;
    }

    console.log('Total rows to update:', payload.length);

    // Function to chunk array
    function chunkArray(arr, size) {
        var chunks = [];
        for (var i = 0; i < arr.length; i += size) {
            chunks.push(arr.slice(i, i + size));
        }
        return chunks;
    }

    var chunks = chunkArray(payload, 200);
    var currentChunk = 0;

    function sendNextChunk() {
        if (currentChunk >= chunks.length) {
            // All done
            $("#loader").hide();
            $("#tableContainer").show();
            $("#updateBtn").prop("disabled", false).text("Update");
            $("#synmessege").hide();
            alert("All rows updated successfully!");
            return;
        }

        var chunkData = chunks[currentChunk];
        //console.log(`Sending batch ${currentChunk + 1} of ${chunks.length}`);

        $.ajax({
            url: '{{ secure_url('shopify/prices-update') }}',
            method: 'POST',
            timeout: 0,
            contentType: 'application/json',
            data: JSON.stringify({ data: chunkData }),
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function (response) {
                //console.log(`Batch ${currentChunk + 1} update success`);
                currentChunk++;
                // Wait 2 seconds before sending next batch
                setTimeout(sendNextChunk, 2000);
            },
            error: function (xhr) {
                //console.error(`Error updating batch ${currentChunk + 1}:`, xhr.responseText);
                alert("Error updating batch " + (currentChunk + 1) + ". Check console.");
                $("#loader").hide();
                $("#tableContainer").show();
                $("#updateBtn").prop("disabled", false).text("Update");
            }
        });
    }

    // Start sending first batch
    sendNextChunk();
});





$(document).on('click', '#InventoryBtn', function () {
    $("#tabledropbox").hide();
    $("#synmessege").show();
  
  
    var table = $('#dropboxTable').DataTable();
    $("#loader").show();

    $("#updateBtn").prop("disabled", true).text("Updating...");

    var allData = table.rows().data().toArray();

    var payload = allData
    .map(function (row) {
        if (row[1] != 0 && row[1] !== '') {
            return {
                sku: row[0],
                available: row[1], // If MAP exists, use it, otherwise use retail
                Upc_code: row[3]   // Retail price
            };
        }
        return null; // Skip this row
    })
    .filter(function (item) {
        return item && item.sku !== '' && item.available !== '';
    });


    if (payload.length === 0) {
        alert("No valid data to update.");
        $("#loader").hide();
        $("#tabledropbox").show();
        $("#updateBtn").prop("disabled", false).text("Update");
        return;
    }
     //console.log('Total', payload)
    //console.log('Total rows to update:', payload.length);

    // Function to chunk array
    function chunkArray(arr, size) {
        var chunks = [];
        for (var i = 0; i < arr.length; i += size) {
            chunks.push(arr.slice(i, i + size));
        }
        return chunks;
    }

    var chunks = chunkArray(payload, 100);
    var currentChunk = 0;

    function sendNextChunk() {
        if (currentChunk >= chunks.length) {
            // All done
            $("#loader").hide();
            $("#tabledropbox").show();
            $("#updateBtn").prop("disabled", false).text("Update");
            $("#synmessege").hide();
            alert("All rows updated successfully!");
            return;
        }

        var chunkData = chunks[currentChunk];
        console.log(`Sending batch ${currentChunk + 1} of ${chunks.length}`);

        $.ajax({
            url: '{{ secure_url('shopify/inventory') }}',
            method: 'POST',
            timeout: 0,
            contentType: 'application/json',
            data: JSON.stringify({ data: chunkData }),
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function (response) {
                //console.log(`Batch ${currentChunk + 1} update success`);
                currentChunk++;
                // Wait 2 seconds before sending next batch
                setTimeout(sendNextChunk, 2000);
            },
            error: function (xhr) {
                //console.error(`Error updating batch ${currentChunk + 1}:`, xhr.responseText);
                alert("Error updating batch " + (currentChunk + 1) + ". Check console.");
                $("#loader").hide();
                $("#tabledropbox").show();
                $("#updateBtn").prop("disabled", false).text("Update");
            }
        });
    }

    // Start sending first batch
    sendNextChunk();
});


</script>

</body>
</html>
