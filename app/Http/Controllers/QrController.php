<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Str;
use DB;
use App\Models\QRCode as QRCodeModel;
use App\Models\Category;
use App\Models\Item;
use App\Exports\QrCodesExport;
use Maatwebsite\Excel\Facades\Excel;

class QrController extends Controller
{
    public function index()
    {
        $qr_codes = QRCodeModel::orderBy('id', 'desc')->with('categories')->get();
        
        return view('qr.index', compact('qr_codes'));
    }

    public function create()
    {
        $category = Category::get();
        return view(
            'qr.add',
            [
                'qr_code' => null,
                'categories' => $category
            ]
        );
    }

    public function generate(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'total_count' => 'required|integer|min:1',
        ]);
        $category = Category::findOrFail($request->category_id);
        $folder = public_path('images/qr_code');
        if (!file_exists($folder)) {
            mkdir($folder, 0755, true);
        }
        $startNumber = QRCodeModel::where('category_id', $category->id)->count();
        for ($i = 1; $i <= $request->total_count; $i++) {
            $random = strtoupper(Str::random(6));
            $sequenceNumber = $startNumber + $i;
            $rockId = $sequenceNumber . '-' . Str::slug($category->name) . '-' . $random;
            $url    = url('/scan/' . $rockId);
            $qrImage = QrCode::format('svg')->size(300)->generate($url);
            $fileName = $rockId . '.svg';
            $filePath = $folder . '/' . $fileName;
            file_put_contents($filePath, $qrImage);
            $qr_code = new QRCodeModel();
            $qr_code->category_id   = $category->id;
            $qr_code->rock_id       = $rockId;
            $qr_code->qr_image      = 'images/qr_code/' . $fileName;
            $qr_code->is_registered = false;
            $qr_code->save();
        }
        return redirect()
            ->route('qr-code.index')
            ->with('success', $request->total_count . ' QR codes generated successfully!');
    }
    public function print(Request $request){
        $request->validate([
            'qr_ids' => 'required|array'
        ]);
        $qrCodes = QRCodeModel::with('categories')->orderBy('id','desc')->whereIn('id', $request->qr_ids)->get();
        return view('qr.print', compact('qrCodes'));
    }
    
    public function export(Request $request)
    {
        if (!$request->qr_ids) {
            return back()->with('error', 'Please select at least one record');
        }

        return Excel::download(
            new QrCodesExport($request->qr_ids),
            'qr-codes.xlsx'
        );
    }
}
