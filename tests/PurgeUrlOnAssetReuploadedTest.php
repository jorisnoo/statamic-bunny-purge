<?php

use Illuminate\Support\Facades\Bus;
use Noo\StatamicBunnyPurge\Jobs\PurgeUrlJob;
use Noo\StatamicBunnyPurge\Listeners\PurgeUrlOnAssetReuploaded;
use Statamic\Events\AssetReuploaded;

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
