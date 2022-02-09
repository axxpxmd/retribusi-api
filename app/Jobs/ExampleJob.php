<?php

namespace App\Jobs;

use App\Http\Controllers\BJB\CallBackController;
use Illuminate\Support\Facades\Http;

class ExampleJob extends Job
{
    protected $reqBody;
    protected $url;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($reqBody, $url)
    {
        $this->reqBody = $reqBody;
        $this->url = $url;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $url = $this->url;
        $reqBody = $this->reqBody;

        $res = Http::get($url, $reqBody);

        if ($res->successful()) {
            $resJson = $res->json();
            if ($resJson['status'] == 200) {
                echo 'berhasil';
            }
        } else {
            echo 'failed';
        }
    }
}
