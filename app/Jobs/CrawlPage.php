<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use \App\Services\PageService;

class CrawlPage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $url;
    public $testMode = false;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(string $url) {
        $this->url = $url;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() {
        $pageService = new PageService($this->url);

        $data = [
            'title'         => $pageService->getTitle(),
            'description'   => $pageService->getDescription(),
            'url'           => $pageService->url,
            'links'         => $pageService->getLinks(),
            'images'        => $pageService->getImages(),
            'phrases'       => $pageService->getPhrases(),
            'opengraph'     => $pageService->getOpenGraph(),
            'screenshot'    => $pageService->image
        ];

        if($this->testMode){
            return $data;
        }

        // This object is too big for SQS, we will need to send it directly to the index
        // rather than have another job do this for us
        // TODO: Send data to the index, the index doesn't exist yet
        //DeliverIndex::dispatch($data);
        return;
    }
}
