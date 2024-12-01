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
    public function showJudge()
    {
        return view('cvds.judge');
    }
    // judge 結果の処理
    public function judgeResult(Request $request)
    {
        $typeResult = $request->input('type_result');

        if ($typeResult == 'undetermined') {
            // 判定不能の場合、メッセージを表示して戻す
            return redirect()->route('cvds.judge')->with('message', '判定が難しい結果となりました。もう一度お試しください。');
        }

        // 判定結果をセッションに保存
        $request->session()->put('type_result', $typeResult);

        // measure ページにリダイレクト
        return redirect()->route('measure');
    }
    // measure ページの表示
    public function showMeasure(Request $request)
    {
        $typeResult = $request->session()->get('type_result');

        if (!$typeResult) {
            // セッションにデータがない場合、judge ページに戻す
            return redirect()->route('cvds.judge');
        }

        return view('cvds.measure', ['typeResult' => $typeResult]);
    }
    // measure 結果の処理
    public function measureResult(Request $request)
    {
        $measurementDataJson = $request->input('measurement_data');
        $measurementData = json_decode($measurementDataJson, true);

        // 強度を計算
        $intensity = $this->calculateIntensity($measurementData);

        // セッションから type_result を取得
        $typeResult = $request->session()->get('type_result');

        // タイプに応じて閾値を設定
        if ($typeResult == 'type1') {
            $threshold = 15;
        } elseif ($typeResult == 'type2') {
            $threshold = 22;
        } else {
            // 判定不能または不明なタイプの場合、judge ページに戻す
            return redirect()->route('judge')->with('message', '色覚異常のタイプが不明です。もう一度お試しください。');
        }

        // 強度をセッションに保存
        $request->session()->put('intensity', $intensity);

        // 強度に応じてページをリダイレクト
        if ($intensity > $threshold) {
            return redirect()->route('strong');
        } else {
            return redirect()->route('weak');
        }
    }
    // 強度計算のヘルパーメソッド
    private function calculateIntensity($data)
    {
        $totalAverage = 0;
        $count = 0;
 
        foreach ($data['positive'] as $deltaE) {
            $totalAverage += $deltaE;
            $count++;
        }
         foreach ($data['negative'] as $deltaE) {
            $totalAverage += $deltaE;
            $count++;
        }

        if ($count === 0) {
            return 0;
        }
 
        return $totalAverage / $count;
    }
    // strong ページの表示
    public function showStrong(Request $request)
    {
        $intensity = $request->session()->get('intensity');
        return view('cvds.strong', ['intensity' => $intensity]);
    }

    // weak ページの表示
    public function showWeak(Request $request)
    {
        // セッションから値を取得し、デフォルト値も設定
        $intensity = $request->session()->get('intensity', 0);
        $typeResult = $request->session()->get('type_result', 'type1');
        
        // 色覚タイプを変換
        $deficiencyType = 'protan'; // デフォルト値
        if ($typeResult == 'type1') {
            $deficiencyType = 'protan';
        } elseif ($typeResult == 'type2') {
            $deficiencyType = 'deutan';
        }

        // ビューに渡すデータを配列にまとめる
        $data = [
            'intensity' => $intensity,
            'deficiencyType' => $deficiencyType,
            'numericalValue' => $intensity ? round($intensity) : 0
        ];

        return view('cvds.weak', $data);
    }
}


