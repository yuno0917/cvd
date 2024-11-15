<!-- resources/views/cvds/strong.blade.php -->

<!DOCTYPE html>
<html>
<head>
    <title>色覚補正アプリ</title>
</head>
<body>
    <h1>カメラから画像を取得</h1>
    <video id="video" width="640" height="480" autoplay></video>
    <button id="snap">写真を撮る</button>
    <canvas id="canvas" width="640" height="480" style="display: none;"></canvas>
    <form id="image-form" method="POST" enctype="multipart/form-data" action="{{ route('process-image') }}">
        @csrf
        <input type="hidden" name="image" id="image-input">
        <button type="submit">画像を送信</button>
    </form>

    <script>
        // カメラ映像を表示
        const video = document.getElementById('video');

        navigator.mediaDevices.getUserMedia({ video: true })
            .then(stream => { video.srcObject = stream; })
            .catch(err => { console.error('エラー:', err); });

        // 写真を撮る
        const canvas = document.getElementById('canvas');
        const snap = document.getElementById('snap');
        const imageInput = document.getElementById('image-input');

        snap.addEventListener('click', () => {
            const context = canvas.getContext('2d');
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            // 画像データを取得
            const dataURL = canvas.toDataURL('image/png');
            imageInput.value = dataURL;
        });
    </script>

    @if(isset($processedImage))
        <h2>補正済み画像</h2>
        <img src="{{ $processedImage }}" alt="補正済み画像">
    @endif
</body>
</html>
