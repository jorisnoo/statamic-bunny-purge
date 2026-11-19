<?php

use Illuminate\Support\Env;
use Illuminate\Support\Facades\Bus;
use Noo\StatamicBunnyPurge\Jobs\PurgeUrlJob;
use Noo\StatamicBunnyPurge\Listeners\PurgeUrlOnAssetReuploaded;
use Statamic\Assets\Asset;
use Statamic\Events\AssetDeleted;
use Statamic\Events\AssetReuploaded;
use Statamic\Events\AssetSaved;
use Statamic\Facades\URL;

function fakeAsset(string $containerHandle, string $absoluteUrl): object
{
    $container = Mockery::mock();
    $container->shouldReceive('handle')->andReturn($containerHandle);

    $asset = Mockery::mock();
    $asset->shouldReceive('container')->andReturn($container);
    $asset->shouldReceive('absoluteUrl')->andReturn($absoluteUrl);
    $asset->shouldReceive('path')->andReturn('report.pdf');

    return $asset;
}

it('dispatches a purge job when the asset container is configured', function () {
    Bus::fake([PurgeUrlJob::class]);
    config()->set('statamic.bunny-purge.purge_asset_containers', ['downloads']);

    $asset = fakeAsset('downloads', 'https://example.com/storage/downloads/report.pdf');
    $event = new AssetReuploaded($asset, 'report.pdf');

    (new PurgeUrlOnAssetReuploaded)->handle($event);

    Bus::assertDispatched(PurgeUrlJob::class, function ($job) {
        return $job->url === 'https://example.com/storage/downloads/report.pdf';
    });
});

it('does not dispatch when the asset container is not configured', function () {
    Bus::fake([PurgeUrlJob::class]);
    config()->set('statamic.bunny-purge.purge_asset_containers', ['downloads']);

    $asset = fakeAsset('images', 'https://example.com/storage/images/photo.jpg');
    $event = new AssetReuploaded($asset, 'photo.jpg');

    (new PurgeUrlOnAssetReuploaded)->handle($event);

    Bus::assertNotDispatched(PurgeUrlJob::class);
});

it('does not dispatch when no containers are configured', function () {
    Bus::fake([PurgeUrlJob::class]);
    config()->set('statamic.bunny-purge.purge_asset_containers', []);

    $asset = fakeAsset('downloads', 'https://example.com/storage/downloads/report.pdf');
    $event = new AssetReuploaded($asset, 'report.pdf');

    (new PurgeUrlOnAssetReuploaded)->handle($event);

    Bus::assertNotDispatched(PurgeUrlJob::class);
});

it('uses the previous public URL on rename and ignores private assets for all three events', function (bool $private) {
    Bus::fake();
    config(['statamic.bunny-purge.purge_asset_containers' => ['downloads']]);
    $container = Mockery::mock();
    $container->shouldReceive('handle')->andReturn('downloads');
    $container->shouldReceive('private')->andReturn($private);
    $container->shouldReceive('absoluteUrl')->andReturn('https://example.com/files');
    URL::shouldReceive('assemble')->andReturnUsing(fn (...$parts) => implode('/', $parts));

    $asset = new class extends Asset
    {
        public function getOriginal($key = null, $fallback = null)
        {
            return 'reports/my photo.jpg';
        }
    };
    $asset->container($container)->path('reports/my photo.jpg');
    $oldUrl = $asset->absoluteUrl();
    $asset->path('reports/new.jpg');
    event(new AssetSaved($asset));
    expect($asset->path())->toBe('reports/new.jpg');
    event(new AssetDeleted($asset));
    event(new AssetReuploaded($asset, $asset->path()));

    if ($private) {
        Bus::assertNothingDispatched();
    } else {
        Bus::assertDispatched(PurgeUrlJob::class, fn ($job) => $job->url === $oldUrl);
        Bus::assertDispatched(PurgeUrlJob::class, fn ($job) => $job->url === $asset->absoluteUrl());
    }
})->with([true, false]);

it('trims comma-separated container handles and ignores empty entries', function () {
    $env = Env::getRepository();
    $env->set('CDN_PURGE_ASSET_CONTAINERS', 'downloads, , images,');
    try {
        $settings = require __DIR__.'/../config/bunny-purge.php';
        expect($settings['purge_asset_containers'])->toBe(['downloads', 'images']);
    } finally {
        $env->clear('CDN_PURGE_ASSET_CONTAINERS');
    }
});
