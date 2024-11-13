@extends('layouts.app')

@section('title', 'ジャンルを選択してください')

@section('content')
<div class="">
    <h1>ジャンルを選択してください</h1>
    <div class="genre-container">
        <button onclick="location.href='{{ route('books.book', ['genre_id' => '001004001']) }}'" class="genre-button purple">ミステリー、サスペンス</button>
        <button onclick="location.href='{{ route('books.book', ['genre_id' => '001004002']) }}'" class="genre-button blue">SF、ホラー</button>
        <button onclick="location.href='{{ route('books.book', ['genre_id' => '001004003']) }}'" class="genre-button pink">エッセイ</button>
        <button onclick="location.href='{{ route('books.book', ['genre_id' => '001004004']) }}'" class="genre-button green">ノンフィクション</button>
        <button onclick="location.href='{{ route('books.book', ['genre_id' => '001017']) }}'" class="genre-button brown">ライトノベル</button>
        <button onclick="location.href='{{ route('books.book', ['genre_id' => '001']) }}'" class="genre-button other">その他</button>
    </div>
</div>
@endsection
