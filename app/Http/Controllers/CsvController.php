<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CsvController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv'
        ]);

        $file = $request->file('excel_file');
        $spreadsheet = IOFactory::load($file->getPathname());
        $sheetData = $spreadsheet->getActiveSheet()->toArray();

        return response()->json([
            'filename' => $file->getClientOriginalName(),
            'data' => $sheetData
        ]);
    }
}
