<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>色覚異常強度測定システム</title>
    <style>
        .container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
            padding: 20px;
        }
        .stimuli-container {
            display: flex;
            gap: 40px;
            align-items: center;
        }
        .color-swatch {
            width: 150px;
            height: 150px;
            border: 1px solid #ccc;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .controls {
            display: flex;
            flex-direction: column;
            gap: 15px;
            align-items: center;
            width: 300px;
        }
        .fine-controls {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        button {
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
        }
        .value-display {
            font-size: 18px;
            font-weight: bold;
            min-width: 100px;
            text-align: center;
        }
        .slider-container {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 5px;
            align-items: center;
        }
        .slider {
            width: 100%;
            height: 25px;
        }
        .slider-label {
            font-size: 14px;
            color: #666;
        }
        .results {
            margin-top: 20px;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .color-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>色覚異常強度測定システム</h1>

        <!-- 測定結果送信用フォーム -->
        <form id="measure-form" action="{{ route('measure.result') }}" method="POST">
            @csrf
            <input type="hidden" name="measurement_data" id="measurement_data">
            <div class="color-name" id="current-color">灰色</div>
            <div class="stimuli-container">
                <div>
                    <h2>参照刺激</h2>
                    <div id="reference" class="color-swatch"></div>
                </div>
                <div>
                    <h2>変調刺激</h2>
                    <div id="test" class="color-swatch"></div>
                </div>
            </div>
            <div class="controls">
                <div class="slider-container">
                    <input type="range" id="deltaE-slider" class="slider" 
                           min="0" max="80" value="0" step="0.1">
                    <div class="slider-label">ΔE値をスライダーで調整（0-80）</div>
                </div>
                <div class="fine-controls">
                    <button type="button" id="decrease">ΔE - 1</button>
                    <div class="value-display">ΔE = <span id="deltaE-value">0</span></div>
                    <button type="button" id="increase">ΔE + 1</button>
                </div>
                <button type="button" id="next-color">次の色へ</button>
                <button type="button" id="show-results">結果を表示</button>
            </div>
            <!-- 結果を送信するボタン（非表示） -->
            <button type="submit" id="submit-measurement" style="display: none;">結果を送信</button>
        </form>

        <!-- 測定結果を表示するエリア -->
        <div id="results" class="results" style="display: none;">
            <h2>測定結果</h2>
            <div id="results-content"></div>
        </div>
    </div>

    <script>
        // コントローラーから渡された typeResult を使用
        let currentType = {{ json_encode($typeResult == 'type1' ? 1 : 2) }};

        // 色のデータ定義
        const colorDataType1 = {
            Gray: { Y: 25.468, x: 0.313, y: 0.329, name: "灰色" },
            Yellow: { Y: 25.468, x: 0.403, y: 0.485, name: "黄色" },
            Purple: { Y: 25.468, x: 0.343, y: 0.219, name: "紫色" },
            Orange: { Y: 25.468, x: 0.523, y: 0.405, name: "橙色" },
            Green: { Y: 25.468, x: 0.328, y: 0.537, name: "緑色" },
            Red: { Y: 25.468, x: 0.432, y: 0.306, name: "赤色" },
            Cyan: { Y: 25.468, x: 0.264, y: 0.338, name: "青緑色" },
            Blue: { Y: 25.468, x: 0.246, y: 0.215, name: "青色" }
        };

        const colorDataType2 = {
            Gray: { Y: 25.468, x: 0.313, y: 0.329, name: "灰色" },
            Yellow: { Y: 25.468, x: 0.403, y: 0.485, name: "黄色" },
            Purple: { Y: 25.468, x: 0.343, y: 0.219, name: "紫色" },
            Orange: { Y: 25.468, x: 0.502, y: 0.398, name: "橙色" },
            Green: { Y: 25.468, x: 0.354, y: 0.530, name: "緑色" },
            Red: { Y: 25.468, x: 0.392, y: 0.275, name: "赤色" },
            Cyan: { Y: 25.468, x: 0.267, y: 0.360, name: "青緑色" },
            Blue: { Y: 25.468, x: 0.253, y: 0.276, name: "青色" }
        };

        // 収束点の定義
        const convergencePointType1 = { x: 0.7506, y: 0.2495, Y: 25.468 };
        const convergencePointType2 = { x: 1.4292, y: -0.4292, Y: 25.468 };

        // 現在使用するデータ
        let colorData = (currentType === 1) ? colorDataType1 : colorDataType2;
        let convergencePoint = (currentType === 1) ? convergencePointType1 : convergencePointType2;

        // 参照白色点（D65）
        const refWhite = { X: 95.047, Y: 100.000, Z: 108.883 };

        // 測定結果の保存用
        let results = {
            positive: {},  // 正方向の結果
            negative: {}   // 負方向の結果
        };

        // 現在の測定状態
        let currentState = {
            colorKey: 'Gray',
            isPositiveDirection: true,
            colorIndex: 0
        };

        // xyYからXYZへの変換
        function xyYtoXYZ(x, y, Y) {
            const X = (x * Y) / y;
            const Z = ((1 - x - y) * Y) / y;
            return { X, Y, Z };
        }

        // XYZからLabへの変換
        function XYZtoLab(X, Y, Z) {
            function f(t) {
                return t > 0.008856 ? Math.cbrt(t) : (7.787 * t) + (16 / 116);
            }
            const xr = X / refWhite.X;
            const yr = Y / refWhite.Y;
            const zr = Z / refWhite.Z;

            const fx = f(xr);
            const fy = f(yr);
            const fz = f(zr);

            const L = (116 * fy) - 16;
            const a = 500 * (fx - fy);
            const b = 200 * (fy - fz);

            return { L, a, b };
        }

        // ΔEの計算（CIE76）
        function deltaE(lab1, lab2) {
            return Math.sqrt(
                Math.pow(lab1.L - lab2.L, 2) +
                Math.pow(lab1.a - lab2.a, 2) +
                Math.pow(lab1.b - lab2.b, 2)
            );
        }

        // XYZからsRGBへの変換
        function XYZtosRGB(X, Y, Z) {
            let var_R = X *  3.2406 + Y * (-1.5372) + Z * (-0.4986);
            let var_G = X * (-0.9689) + Y *  1.8758 + Z *  0.0415;
            let var_B = X *  0.0557 + Y * (-0.2040) + Z *  1.0570;

            function gammaCorrection(channel) {
                return channel > 0.0031308
                    ? 1.055 * Math.pow(channel, 1 / 2.4) - 0.055
                    : 12.92 * channel;
            }

            var_R = gammaCorrection(var_R / 100);
            var_G = gammaCorrection(var_G / 100);
            var_B = gammaCorrection(var_B / 100);

            var_R = Math.min(Math.max(0, var_R), 1);
            var_G = Math.min(Math.max(0, var_G), 1);
            var_B = Math.min(Math.max(0, var_B), 1);

            return {
                R: Math.round(var_R * 255),
                G: Math.round(var_G * 255),
                B: Math.round(var_B * 255)
            };
        }

        // 目標のΔEに対応する色を計算する関数（方向指定対応）
        function calculateColorForDeltaE(targetDeltaE, isPositive) {
            let t = 0;
            const tIncrement = 0.0001;
            const currentColor = colorData[currentState.colorKey];
            
            const baseXYZ = xyYtoXYZ(currentColor.x, currentColor.y, currentColor.Y);
            const baseLab = XYZtoLab(baseXYZ.X, baseXYZ.Y, baseXYZ.Z);
            
            while (t <= 1) {
                // 方向に応じて計算式を変更
                const x = isPositive ?
                    currentColor.x + t * (convergencePoint.x - currentColor.x) :
                    currentColor.x - t * (convergencePoint.x - currentColor.x);
                const y = isPositive ?
                    currentColor.y + t * (convergencePoint.y - currentColor.y) :
                    currentColor.y - t * (convergencePoint.y - currentColor.y);
                const Y = currentColor.Y;
                
                // 色度座標が有効な範囲内にあるか確認
                if (x < 0 || x > 1 || y < 0 || y > 1 || x + y > 1) {
                    t += tIncrement;
                    continue;
                }

                const XYZ = xyYtoXYZ(x, y, Y);
                const Lab = XYZtoLab(XYZ.X, XYZ.Y, XYZ.Z);
                
                const currentDeltaE = deltaE(baseLab, Lab);
                
                if (Math.abs(currentDeltaE - targetDeltaE) < 0.1) {
                    return XYZtosRGB(XYZ.X, XYZ.Y, XYZ.Z);
                }
                
                t += tIncrement;
            }
            
            return null;
        }

        // 結果を保存する関数
        function saveResult() {
            const direction = currentState.isPositiveDirection ? 'positive' : 'negative';
            results[direction][currentState.colorKey] = currentDeltaE;
        }

        // 次の測定に進む関数
        function nextMeasurement() {
            saveResult();  // 現在の結果を保存

            const colorKeys = Object.keys(colorData);
            const totalMeasurements = colorKeys.length * 2; // 正方向と負方向

            // 測定が完了した場合
            if (currentState.colorIndex === colorKeys.length - 1 && !currentState.isPositiveDirection) {
                document.getElementById('next-color').style.display = 'none';
                document.getElementById('show-results').style.display = 'block';
                return;
            }

            // 通常の状態遷移
            if (currentState.isPositiveDirection) {
                currentState.isPositiveDirection = false;
            } else {
                currentState.isPositiveDirection = true;
                currentState.colorIndex = (currentState.colorIndex + 1) % colorKeys.length;
                currentState.colorKey = colorKeys[currentState.colorIndex];
            }

            // UI更新
            currentDeltaE = 0;
            document.getElementById('current-color').textContent = 
            `${colorData[currentState.colorKey].name} (${currentState.isPositiveDirection ? '正' : '負'}方向)`;
            updateColor();
        }

        // 結果を表示する関数
        function showResults() {
            // 最後の測定結果を保存
            saveResult();

            // 結果を取得
            const measurementDataInput = document.getElementById('measurement_data');
            measurementDataInput.value = JSON.stringify(results);

            // フォームを送信
            document.getElementById('submit-measurement').click();
        }

        // UI初期化とイベントリスナー設定
        let currentDeltaE = 0;
        const deltaEValue = document.getElementById('deltaE-value');
        const increaseButton = document.getElementById('increase');
        const decreaseButton = document.getElementById('decrease');
        const deltaESlider = document.getElementById('deltaE-slider');
        const referenceStimulus = document.getElementById('reference');
        const testStimulus = document.getElementById('test');
        const nextColorButton = document.getElementById('next-color');
        const showResultsButton = document.getElementById('show-results');
        const currentColorName = document.getElementById('current-color');

        // ボタンイベントリスナー
        nextColorButton.addEventListener('click', nextMeasurement);
        showResultsButton.addEventListener('click', showResults);
        showResultsButton.style.display = 'none';

        // 色を更新する関数
        function updateColor() {
            const currentColor = colorData[currentState.colorKey];
            const baseXYZ = xyYtoXYZ(currentColor.x, currentColor.y, currentColor.Y);
            const baseRGB = XYZtosRGB(baseXYZ.X, baseXYZ.Y, baseXYZ.Z);
            referenceStimulus.style.backgroundColor = 
                `rgb(${baseRGB.R}, ${baseRGB.G}, ${baseRGB.B})`;

            const testRGB = calculateColorForDeltaE(currentDeltaE, currentState.isPositiveDirection);
            if (testRGB) {
                testStimulus.style.backgroundColor = 
                    `rgb(${testRGB.R}, ${testRGB.G}, ${testRGB.B})`;
                deltaEValue.textContent = currentDeltaE.toFixed(1);
                deltaESlider.value = currentDeltaE;
            } else {
                testStimulus.style.backgroundColor = 'rgb(255, 255, 255)';
            }

            currentColorName.textContent = 
                `${currentColor.name} (${currentState.isPositiveDirection ? '正' : '負'}方向)`;
        }

        // ボタンのイベントリスナー
        increaseButton.addEventListener('click', () => {
            if (currentDeltaE < 80) {
                currentDeltaE = Math.min(80, currentDeltaE + 1);
                updateColor();
            }
        });

        decreaseButton.addEventListener('click', () => {
            if (currentDeltaE > 0) {
                currentDeltaE = Math.max(0, currentDeltaE - 1);
                updateColor();
            }
        });

        // スライダーのイベントリスナー
        deltaESlider.addEventListener('input', () => {
            currentDeltaE = parseFloat(deltaESlider.value);
            updateColor();
        });

        // 初期表示
        updateColor();

    </script>
</body>
</html>
