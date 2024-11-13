<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class PythonController extends Controller
{
    public function runScript()
    {
        try {
            $process = new Process(['python3', base_path('app/Python/test.py'), 'World']);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $output = $process->getOutput();
            return view('result', ['output' => $output]);

        } catch (ProcessFailedException $e) {
            \Log::error('Process failed: ' . $e->getMessage());
            return view('error', ['message' => 'Pythonスクリプトの実行に失敗しました。']);
        } catch (\Exception $e) {
            \Log::error('An error occurred: ' . $e->getMessage());
            return view('error', ['message' => '予期しないエラーが発生しました。']);
        }
    }


}
