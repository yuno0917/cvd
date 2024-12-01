<!DOCTYPE html>
<html>
<head>
    <title>色覚補正アプリ</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; img-src 'self' data: blob:; media-src 'self' blob:; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';">
</head>
<body>
    <h1>カメラから画像を取得</h1>
    <div id="camera-container">
        <video id="video" width="100%" height="auto" playsinline autoplay></video>
        <canvas id="canvas" width="640" height="480" style="display: none;"></canvas>
    </div>
    <form id="image-form" method="POST" enctype="multipart/form-data" action="{{ route('process-image') }}">
        @csrf
        <input type="hidden" name="image" id="image-input">
        <input type="hidden" name="deficiency_type" value="{{ $deficiencyType ?? 'd' }}">
        <button type="button" id="capture-submit" style="font-size: 1.2em; padding: 10px 20px; margin: 10px 0;">撮影して送信</button>
    </form>
    
    <script>
        async function initCamera() {
            try {
                const constraints = {
                    video: {
                        facingMode: 'environment', // バックカメラを優先
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    }
                };

                const stream = await navigator.mediaDevices.getUserMedia(constraints);
                const video = document.getElementById('video');
                video.srcObject = stream;
                
                // プレイ開始を待機
                await video.play().catch(error => {
                    console.error('Video playback failed:', error);
                });

                console.log('Camera initialized successfully');
            } catch (err) {
                console.error('Camera initialization error:', err);
                alert('カメラの起動に失敗しました。カメラへのアクセスを許可してください。');
            }
        }

        // カメラの初期化
        document.addEventListener('DOMContentLoaded', initCamera);

        // 撮影して送信
        const canvas = document.getElementById('canvas');
        const captureSubmit = document.getElementById('capture-submit');
        const imageInput = document.getElementById('image-input');
        const video = document.getElementById('video');

        captureSubmit.addEventListener('click', () => {
            try {
                // キャンバスのサイズをビデオの表示サイズに合わせる
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                
                const context = canvas.getContext('2d');
                context.drawImage(video, 0, 0, canvas.width, canvas.height);
                
                // 画像データを取得
                const dataURL = canvas.toDataURL('image/png');
                imageInput.value = dataURL;
                
                // フォームを送信
                document.getElementById('image-form').submit();
            } catch (error) {
                console.error('Capture error:', error);
                alert('画像の取得に失敗しました。もう一度お試しください。');
            }
        });
    </script>

    <style>
        #camera-container {
            width: 100%;
            max-width: 640px;
            margin: 0 auto;
        }
        
        #video {
            width: 100%;
            max-width: 640px;
            height: auto;
        }
        
        button {
            display: block;
            margin: 10px auto;
        }
    </style>

    @if(isset($processedImage))
        <h2>補正済み画像</h2>
        <img src="{{ $processedImage }}" alt="補正済み画像" style="max-width: 100%; height: auto;">
    @endif
</body>
</html>