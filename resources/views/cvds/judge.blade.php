<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>色覚異常タイプ判定システム</title>
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
        .choice-buttons {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>色覚異常タイプ判定システム</h1>
        <div class="color-name" id="current-color">灰色（参照刺激）</div>
        <div class="stimuli-container">
            <div>
                <h2>1型変調刺激（左）</h2>
                <div id="test-type1" class="color-swatch"></div>
            </div>
            <div>
                <h2>参照刺激（中央）</h2>
                <div id="reference" class="color-swatch"></div>
            </div>
            <div>
                <h2>2型変調刺激（右）</h2>
                <div id="test-type2" class="color-swatch"></div>
            </div>
        </div>
        <div class="controls">
            <div class="slider-container">
                <input type="range" id="deltaE-slider" class="slider" 
                       min="0" max="80" value="0" step="0.1">
                <div class="slider-label">ΔE値をスライダーで調整（0-80）</div>
            </div>
            <div class="fine-controls">
                <button id="decrease">ΔE - 1</button>
                <div class="value-display">ΔE = <span id="deltaE-value">0</span></div>
                <button id="increase">ΔE + 1</button>
            </div>
            <div class="choice-buttons">
                <button id="left-different">左が先に違う</button>
                <button id="right-different">右が先に違う</button>
            </div>
            <button id="show-results" style="display: none;">結果を表示</button>
        </div>
        <div id="results" class="results" style="display: none;">
            <h2>判定結果</h2>
            <div id="results-content"></div>
        </div>
    </div>

    <script>
        // テストする色のリスト（正方向と負方向を含む）
        const colorsToTest = [
            { Y: 25.468, x: 0.313, y: 0.329, name: "灰色（正方向）", key: "Gray_Positive", direction: "positive" },
            { Y: 25.468, x: 0.313, y: 0.329, name: "灰色（負方向）", key: "Gray_Negative", direction: "negative" },
            { Y: 25.468, x: 0.343, y: 0.219, name: "紫色（正方向）", key: "Purple_Positive", direction: "positive" },
            { Y: 25.468, x: 0.343, y: 0.219, name: "紫色（負方向）", key: "Purple_Negative", direction: "negative" }
        ];

        // 収束点の定義
        const convergencePointType1 = { x: 0.747, y: 0.253 }; // 近似値
        const convergencePointType2 = { x: 1.08, y: -0.8 };   // 近似値

        // 参照白色点（D65）
        const refWhite = { X: 95.047, Y: 100.000, Z: 108.883 };

        // 測定状態
        let currentColorIndex = 0;
        let currentDeltaE = 0;
        let userSelections = [];  // ユーザーの選択を保存

        // HTML要素の取得
        const deltaEValue = document.getElementById('deltaE-value');
        const increaseButton = document.getElementById('increase');
        const decreaseButton = document.getElementById('decrease');
        const deltaESlider = document.getElementById('deltaE-slider');
        const referenceStimulus = document.getElementById('reference');
        const testStimulusType1 = document.getElementById('test-type1');
        const testStimulusType2 = document.getElementById('test-type2');
        const leftDifferentButton = document.getElementById('left-different');
        const rightDifferentButton = document.getElementById('right-different');
        const showResultsButton = document.getElementById('show-results');
        const currentColorName = document.getElementById('current-color');

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

            // クリッピング
            var_R = Math.min(Math.max(0, var_R), 1);
            var_G = Math.min(Math.max(0, var_G), 1);
            var_B = Math.min(Math.max(0, var_B), 1);

            return {
                R: Math.round(var_R * 255),
                G: Math.round(var_G * 255),
                B: Math.round(var_B * 255)
            };
        }

        // 混同線の傾きを計算する関数
        function calculateConfusionLineSlope(baseColor, convergencePoint) {
            return (convergencePoint.y - baseColor.y) / (convergencePoint.x - baseColor.x);
        }

        // 混同線上の点を計算する関数
        function calculateColorOnConfusionLine(baseColor, slope, t, direction) {
            const angle = Math.atan(slope);
            const dx = t * Math.cos(angle);
            const dy = t * Math.sin(angle);

            const x = direction === 'positive' ? baseColor.x + dx : baseColor.x - dx;
            const y = direction === 'positive' ? baseColor.y + dy : baseColor.y - dy;

            return { x, y };
        }

        // 目標のΔEに対応する色を計算する関数
        function calculateColorForDeltaE(targetDeltaE, convergencePoint, baseColor, direction) {
            const slope = calculateConfusionLineSlope(baseColor, convergencePoint);
            let t = 0;
            const tIncrement = 0.0001;
            const baseXYZ = xyYtoXYZ(baseColor.x, baseColor.y, baseColor.Y);
            const baseLab = XYZtoLab(baseXYZ.X, baseXYZ.Y, baseXYZ.Z);

            while (t <= 1) {
                const { x, y } = calculateColorOnConfusionLine(baseColor, slope, t, direction);
                const Y = baseColor.Y;

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

        // 色を更新する関数
        function updateColor() {
            const currentColor = colorsToTest[currentColorIndex];
            currentColorName.textContent = `${currentColor.name}（参照刺激）`;

            const baseXYZ = xyYtoXYZ(currentColor.x, currentColor.y, currentColor.Y);
            const baseRGB = XYZtosRGB(baseXYZ.X, baseXYZ.Y, baseXYZ.Z);
            referenceStimulus.style.backgroundColor = 
                `rgb(${baseRGB.R}, ${baseRGB.G}, ${baseRGB.B})`;

            const testRGBType1 = calculateColorForDeltaE(currentDeltaE, convergencePointType1, currentColor, currentColor.direction);
            if (testRGBType1) {
                testStimulusType1.style.backgroundColor = 
                    `rgb(${testRGBType1.R}, ${testRGBType1.G}, ${testRGBType1.B})`;
            }

            const testRGBType2 = calculateColorForDeltaE(currentDeltaE, convergencePointType2, currentColor, currentColor.direction);
            if (testRGBType2) {
                testStimulusType2.style.backgroundColor = 
                    `rgb(${testRGBType2.R}, ${testRGBType2.G}, ${testRGBType2.B})`;
            }

            deltaEValue.textContent = currentDeltaE.toFixed(1);
            deltaESlider.value = currentDeltaE;
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

        // ユーザーの選択ボタンのイベントリスナー
        leftDifferentButton.addEventListener('click', () => {
            userSelections.push('type1');
            proceedToNext();
        });

        rightDifferentButton.addEventListener('click', () => {
            userSelections.push('type2');
            proceedToNext();
        });

        // 次の色へ進む関数
        function proceedToNext() {
            if (currentColorIndex < colorsToTest.length - 1) {
                currentColorIndex++;
                currentDeltaE = 0;
                updateColor();
            } else {
                showFinalResults();
            }
        }

        // 最終結果を表示する関数
        function showFinalResults() {
            let type1Count = userSelections.filter(selection => selection === 'type1').length;
            let type2Count = userSelections.filter(selection => selection === 'type2').length;

            let resultText = '';

            if (type1Count > type2Count) {
                resultText = `あなたは2型の色覚異常の可能性があります。（${type1Count}対${type2Count}）`;
            } else if (type2Count > type1Count) {
                resultText = `あなたは1型の色覚異常の可能性があります。（${type2Count}対${type1Count}）`;
            } else {
                resultText = '判定が難しい結果となりました。';
            }

            document.getElementById('results-content').textContent = resultText;
            document.getElementById('results').style.display = 'block';

            // ボタンを無効化
            leftDifferentButton.disabled = true;
            rightDifferentButton.disabled = true;
            increaseButton.disabled = true;
            decreaseButton.disabled = true;
            deltaESlider.disabled = true;
        }

        // 初期表示
        updateColor();
    </script>
</body>
</html>
