<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AuditarDetalle;
use App\Http\Controllers\Controller;

class AuditarController extends Controller
{
    public function index(Request $request)
    {
        $keyword = $request->get('search');
        $perPage = 10;
        $query = AuditarDetalle::query();
        if ( !empty( $keyword ) ) {
            $query->where(function ($que) use ($keyword){
                $que->where('created_at', 'LIKE', "%$keyword%");
                $que->orwhereHas('user', function($q) use($keyword){
                    $q->where('name', 'LIKE', "%$keyword%");
                });
                $que->orwhereHas('auditarTabla', function($q) use($keyword){
                    $q->where('tabla', 'LIKE', "%$keyword%");
                });
            });
        }
        $data['auditar'] = $query->latest()->paginate($perPage);
        return view('admin.auditar.index' , $data);
    }

    public function show($id){
        $data['auditar'] = AuditarDetalle::find($id);
        return view('admin.auditar.show', $data);
    }

}
