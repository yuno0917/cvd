@extends('layouts.app')

@section('title', '好きな本')

@section('content')
    <button class="back-button" onclick="window.location.href='{{ route('books.genre') }}'">戻る</button>
    <form id="isbn-form" action="{{ route('books.library', ['genre_id' => $genre_id]) }}" method="GET">
        <div class="isbn-input-container">
            <input type="text" class="isbn-input" name="isbn[]" placeholder="1冊目のISBN">
            <button type="button" class="camera-button">カメラで読み取る</button>
        </div>
        <div class="isbn-input-container">
            <input type="text" class="isbn-input" name="isbn[]" placeholder="2冊目のISBN">
            <button type="button" class="camera-button">カメラで読み取る</button>
        </div>
        <div class="isbn-input-container">
            <input type="text" class="isbn-input" name="isbn[]" placeholder="3冊目のISBN">
            <button type="button" class="camera-button">カメラで読み取る</button>
        </div>
        <button type="submit" class="submit">本を検索</button>
    </form>

    <!-- <div id="result"></div> -->

<script src="https://unpkg.com/@zxing/library@latest"></script>
<script>
let stream;
let codeReader = new ZXing.BrowserMultiFormatReader();

function isValidISBN(code) {
    if (code.length !== 13 && code.length !== 10) return false;
    
    if (code.length === 13 && !code.startsWith('978') && !code.startsWith('979')) return false;
    
    let sum = 0;
    for (let i = 0; i < code.length - 1; i++) {
        sum += parseInt(code[i]) * (i % 2 === 0 ? 1 : 3);
    }
    let checkDigit = (10 - (sum % 10)) % 10;
    return checkDigit === parseInt(code[code.length - 1]);
}

document.querySelectorAll('.camera-button').forEach((button, index) => {
    button.addEventListener('click', async () => {
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } });
            const video = document.createElement('video');
            video.srcObject = stream;
            video.play();
            document.body.appendChild(video);

            const result = await codeReader.decodeFromVideoDevice(undefined, video, (result, err) => {
                if (result) {
                    const scannedCode = result.text.replace(/-/g, '');
                    if (isValidISBN(scannedCode)) {
                        document.getElementsByClassName('isbn-input')[index].value = scannedCode;
                        alert('有効なISBNが読み取られました: ' + scannedCode);

                        stopCamera(video);

                        if (index === 2) {
                            alert('3冊目のISBNを読み取ったのでカメラを閉じます。');
                        }
                    } else {
                        alert('無効なISBNです。ISBNバーコードを読み取ってください。');
                    }
                }
            });
        } catch (error) {
            console.error('Camera error:', error);
            alert('カメラを起動できませんでした。');
        }
    });
});

function stopCamera(video) {
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
    }
    if (video) {
        video.remove();
    }
    codeReader.reset();
}

document.getElementById('isbn-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const isbnInputs = document.getElementsByClassName('isbn-input');
    const isbnList = Array.from(isbnInputs).map(input => input.value).filter(isbn => isbn !== '');

    if (isbnList.length === 0) {
        alert('少なくとも1つのISBNを入力してください。');
        return;
    }

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        
        const response = await fetch('/get-book-info', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ isbn_list: isbnList })
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        // document.getElementById('result').innerText = JSON.stringify(data, null, 2);
        // JSONデータを保存した後、フォームを送信してlibraryページに遷移
        document.getElementById('isbn-form').submit();
    } catch (error) {
        console.error('Error:', error);
        alert('本の情報の取得中にエラーが発生しました。');
    }
});
</script>
@endsection