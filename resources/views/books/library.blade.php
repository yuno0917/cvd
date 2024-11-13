@extends('layouts.app')

@section('title', '推薦図書と図書館情報')

@section('content')
<div class="wrapper">
    <h1>推薦図書と図書館情報</h1>

    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    @if(isset($requires_2fa) && $requires_2fa)
        <form action="{{ route('verify.2fa') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="verification_code">2段階認証コード:</label>
                <input type="text" class="form-control" id="verification_code" name="verification_code" required>
            </div>
            <button type="submit" class="btn btn-primary">確認</button>
        </form>
    @elseif(isset($error))
        <div class="alert alert-danger">
            エラー: {{ $error }}
        </div>
    @elseif(isset($recommendedBooks) && is_array($recommendedBooks) && isset($libraryInfo) && is_array($libraryInfo))
        <div class="carousel">
            <button class="carousel-button prev" onclick="prevSlide()">&#10094;</button>
            <div class="carousel-container">
                @foreach($recommendedBooks as $index => $book)
                    <div class="book-info" style="display: {{ $index === 0 ? 'flex' : 'none' }};">
                        <div class="book-details">
                            <h2 class="book-kind">{{ $book['タイトル'] }}</h2>
                            <p><strong>著者:</strong> {{ $book['著者名'] }}</p>
                            <p><strong>出版社:</strong> {{ $book['出版社名'] }}</p>
                            <p><strong>発売日:</strong> {{ $book['発売日'] }}</p>
                            <p><strong>レビュー:</strong> {{ $book['レビュー平均'] }} ({{ $book['レビュー件数'] }}件)</p>
                            <p><strong>価格:</strong> {{ $book['価格（税込）'] }}円</p>
                            <p><strong>ISBN:</strong> {{ $book['ISBNコード'] }}</p>
                            <a href="{{ $book['商品URL'] }}" class="btn btn-primary" target="_blank">商品詳細</a>
                            <p class="mt-3"><strong>あらすじ:</strong> {{ $book['あらすじ'] }}</p>

                            <h3 class="mt-4">図書館情報</h3>
                            @php
                                $bookLibraryInfo = collect($libraryInfo)->firstWhere('ISBN', $book['ISBNコード']);
                            @endphp
                            @if($bookLibraryInfo && !isset($bookLibraryInfo['error']))
                                <p><strong>図書館名:</strong> {{ $bookLibraryInfo['name'] }}</p>
                                <p><strong>住所:</strong> {{ $bookLibraryInfo['address'] }}</p>
                                <p><strong>距離:</strong> {{ $bookLibraryInfo['distance'] }}</p>
                                <p><strong>状態:</strong> {{ $bookLibraryInfo['status'] }}</p>
                                <a href="{{ $bookLibraryInfo['library_url'] }}" class="btn btn-secondary" target="_blank">図書館の詳細</a>
                            @else
                                <p>この本の図書館情報は見つかりませんでした。</p>
                            @endif
                        </div>
                        <div class="book-image">
                            <img src="{{ $book['商品画像URL'] }}" alt="{{ $book['タイトル'] }}" class="img-fluid">
                        </div>
                    </div>
                @endforeach
            </div>
            <button class="carousel-button next" onclick="nextSlide()">&#10095;</button>
        </div>
    @else
        <p>データが見つかりません。</p>
    @endif
</div>

<button class="home-button" onclick="location.href='{{ route('books.genre') }}'">ホームに戻る</button>

<script>
    let currentIndex = 0;

    function showSlide(index) {
        const items = document.querySelectorAll('.book-info');
        const totalItems = items.length;

        if (index >= totalItems) {
            currentIndex = 0;
        } else if (index < 0) {
            currentIndex = totalItems - 1;
        } else {
            currentIndex = index;
        }

        items.forEach((item, i) => {
            item.style.display = (i === currentIndex) ? 'flex' : 'none';
        });
    }

    function nextSlide() {
        showSlide(currentIndex + 1);
    }

    function prevSlide() {
        showSlide(currentIndex - 1);
    }

    document.addEventListener('DOMContentLoaded', () => {
        showSlide(currentIndex);
    });
</script>
@endsection