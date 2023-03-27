<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class LinkController extends Controller
{
    function generateRandomString($length = 16) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public function createLink(Request $request)
    {
        $path = $request->path;

        $user = Auth::user();
        if ($user == null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], Response::HTTP_UNAUTHORIZED);
        }

        while(1) {
            $link = $this->generateRandomString();

            $oldLink = DB::select(
                'select * from links where link = :link',
                ['link' => $link]
            );

            if ($oldLink == null) {
                break;
            }
        }

        DB::insert(
            'insert into links (link, path, created_at, updated_at) values (?, ?, ?, ?)',
            [$link, $path, new \DateTime(), new \DateTime()]
        );
        $file = \App\Models\File::where('path', $path)->first();
        if ($file->links == null) {
            $file->links = array($link);
        } else {
            $file->links = array_merge($file->links, [$link]);
        }
        $file->save();

        return response()->json([
           'url' => "http://127.0.0.1:8000/api/i/{$link}",
        ]);
    }

    public function openLink(Request $request, $link)
    {
        $path = DB::select(
            'select path from links where link = :link',
            ['link' => $link]
        );
        $fullPath = storage_path() . '/app/root/' . $path[0]->path;
        return response()->download($fullPath);
    }
}
