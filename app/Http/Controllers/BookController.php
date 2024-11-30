<?php

// app/Http/Controllers/BookController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;

class BookController extends Controller
{
    public function processImage(Request $request)
    {
        // Base64 エンコードされた画像データを取得
        $base64Image = $request->input('image');

        // データ URL から Base64 部分を抽出
        $imageData = explode(',', $base64Image)[1];

        // デコードしてバイナリデータに変換
        $imageBinary = base64_decode($imageData);

        // 一時ファイルに保存
        $tempImagePath = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($tempImagePath, $imageBinary);

        // FastAPI サーバーに画像を送信
        $client = new Client([
            'base_uri' => 'http://python:8080', // Docker コンテナ名を使用
        ]);

        $response = $client->post('/daltonize', [
            'multipart' => [
                [
                    'name'     => 'file',
                    'contents' => fopen($tempImagePath, 'r'),
                    'filename' => 'image.png',
                ],
            ],
        ]);

        // レスポンスから補正済み画像を取得
        $processedImage = $response->getBody()->getContents();

        // 一時ファイルを削除
        unlink($tempImagePath);

        // 補正済み画像を Base64 エンコードしてビューに渡す
        $processedImageBase64 = base64_encode($processedImage);
        $dataUrl = 'data:image/png;base64,' . $processedImageBase64;

        return view('cvds.strong', ['processedImage' => $dataUrl]);
    }
    // 新しく追加するメソッド
    public function processWeakImage(Request $request)
    {
        // バリデーション
        $request->validate([
            'deficiency_type' => 'required|string',
            'numerical_value' => 'required|integer',
            'image' => 'required'
        ]);

        // Base64 エンコードされた画像データを取得
        $base64Image = $request->input('image');

        // データ URL から Base64 部分を抽出
        $imageData = explode(',', $base64Image)[1];

        // デコードしてバイナリデータに変換
        $imageBinary = base64_decode($imageData);

        // 一時ファイルに保存
        $tempImagePath = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($tempImagePath, $imageBinary);

        // FastAPI サーバーに画像を送信
        $client = new Client([
            'base_uri' => 'http://python:8080', // Docker コンテナ名を使用
        ]);

        $response = $client->post('/weakhosei', [
            'multipart' => [
                [
                    'name'     => 'deficiency_type',
                    'contents' => $request->input('deficiency_type'),
                ],
                [
                    'name'     => 'numerical_value',
                    'contents' => $request->input('numerical_value'),
                ],
                [
                    'name'     => 'file',
                    'contents' => fopen($tempImagePath, 'r'),
                    'filename' => 'image.png',
                ],
            ],
        ]);

        // レスポンスから補正済み画像を取得
        $processedImage = $response->getBody()->getContents();

        // 一時ファイルを削除
        unlink($tempImagePath);

        // 補正済み画像を Base64 エンコードしてビューに渡す
        $processedImageBase64 = base64_encode($processedImage);
        $dataUrl = 'data:image/png;base64,' . $processedImageBase64;

        return view('cvds.weak', ['processedImage' => $dataUrl]);
    }
}


