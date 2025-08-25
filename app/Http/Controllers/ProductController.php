<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller
{
    public function uploadFile(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt,xlsx,xls|max:5120', // 5MB limit
        ]);

        $file = $request->file('file');
        $data = Excel::toArray([], $file); // returns array of sheets

        // Flatten to first sheet only
        $sheetData = $data[0] ?? [];

        return response()->json($sheetData);
    }
  
   public function test(Request $request)
    {
     dd('dd');
   }
}
