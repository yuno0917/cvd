<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

class BookController extends Controller
{
    public function book(Request $request)
    {
        $genre_id = $request->route('genre_id');
        try {
            return view('books.book', ['genre_id' => $genre_id]);
        } catch (\Exception $e) {
            \Log::error('An error occurred: ' . $e->getMessage());
            return view('error', ['message' => '予期しないエラーが発生しました。エラー: ' . $e->getMessage()]);
        }
    }

    public function getIsbn()
    {
        $response = Http::get('http://python:8080/get-isbn');
        

        return $response->json();
        // $isbn = $response->json()['isbn'];
        // return response()->json(['isbn' => $isbn]);
    }

    public function getBookInfo(Request $request)
    {
        $isbnList = $request->input('isbn_list');
        $response = Http::post('http://python:8080/get-book-info', ['isbn_list' => $isbnList]);
        return $response->json();

        // 以下はjsonデータを返すときの場所、レコメンドで使うので、レコメンドのページに返すように実装すべき
        // $books = $response->json();
        // return view('books.recommend', compact('books'));
    }

    public function library(Request $request)
    {
        $genre_id = $request->route('genre_id');
        // $isbnList = $request->input('isbn');
        $isbnResponse = Http::get('http://python:8080/get-all-isbns');
        if ($isbnResponse->failed()) {
            \Log::error('Failed to get ISBNs: ' . $isbnResponse->body());
            return view('books.library', ['error' => 'ISBNの取得に失敗しました。', 'genre_id' => $genre_id]);
        }
        $initialIsbnList = $isbnResponse->json();
    
        if (Session::get('requires_2fa') && !Session::get('2fa_verified')) {
            return view('books.library', ['requires_2fa' => true, 'genre_id' => $genre_id]);
        }
        try {
            $recommendResponse = Http::timeout(10000)->post('http://python:8080/run-recommend', [
                'genre_id' => $genre_id,
                'isbn_list' => $initialIsbnList
            ]);
    
            if ($recommendResponse->failed()) {
                \Log::error('Recommend API request failed: ' . $recommendResponse->body());
                return view('books.library', ['error' => 'レコメンデーションの取得に失敗しました。', 'genre_id' => $genre_id]);
            }

            $recommendData = $recommendResponse->json();
            if (!is_array($recommendData) || !isset($recommendData['isbn_list']) || !isset($recommendData['recommended_books'])) {
                throw new \Exception('Unexpected response structure from recommendation API');
            }
            \Log::info('Received recommendation data: ' . json_encode($recommendData));
            $recommendedIsbns = $recommendData['isbn_list'];
            $recommendedBooks = $recommendData['recommended_books'];
            // $recommendedIsbns = $recommendResponse->json();
            // $isbnList = $recommendedIsbns;
            // $isbnList = array_merge($initialIsbnList, $recommendedIsbns);

            // ISBNリストが配列であることを確認
            if (!is_array($recommendedIsbns)) {
                throw new \Exception('ISBN list is not in the expected format');
            }

            // 推薦された本の情報が配列であることを確認
            if (!is_array($recommendedBooks)) {
                throw new \Exception('Recommended books data is not in the expected format');
            }
            $response = Http::post('http://python:8080/run-calil', [
                'isbn_list' => $recommendedIsbns
            ]);
    
            if ($response->failed()) {
                \Log::error('API request failed: ' . $response->body());
                return view('books.library', ['error' => 'Python APIの実行に失敗しました。', 'genre_id' => $genre_id]);
            }
    
            $libraryInfo = $response->json();
            
            if (isset($libraryInfo['requires_2fa'])) {
                Session::put('requires_2fa', true);
                return view('books.library', ['requires_2fa' => true, 'genre_id' => $genre_id]);
            }
    
            if (isset($libraryInfo['error'])) {
                return view('books.library', ['error' => $libraryInfo['error'], 'genre_id' => $genre_id]);
            }
    
            // 2FA検証済みフラグをリセット
            Session::forget('2fa_verified');
    
            return view('books.library', [
                'libraryInfo' => $libraryInfo,
                'genre_id' => $genre_id,
                'recommendedBooks' => $recommendedBooks
            ]);
    
        } catch (\Exception $e) {
            \Log::error('An error occurred: ' . $e->getMessage());
            return view('books.library', ['output' => ['error' => '予期しないエラーが発生しました。'], 'genre_id' => $genre_id]);
        }
    }
    
    public function verify2FA(Request $request)
    {
        $genre_id = $request->route('genre_id');
        $verificationCode = $request->input('verification_code');
    
        $response = Http::post('http://python:8080/verify-2fa', [
            'verification_code' => $verificationCode
        ]);
    
        if ($response->failed()) {
            return redirect()->route('books.library', ['genre_id' => $genre_id])->with('error', '2段階認証の検証に失敗しました。');
        }
    
        $result = $response->json();
    
        if (isset($result['error'])) {
            return redirect()->route('books.library', ['genre_id' => $genre_id])->with('error', $result['error']);
        }
    
        // 2FA成功後のセッション更新
        Session::forget('requires_2fa');
        Session::put('2fa_verified', true);
    
        // 図書館情報を再取得
        return $this->library($request);
    }
    
    public function genre()
    {
        return view('books.genre');
    }
}
