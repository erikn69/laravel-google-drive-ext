<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

Route::get('put', function() {
    Storage::disk('google')->put('test.txt', 'Hello World');
    return 'File was saved to Google Drive';
});

Route::get('put-existing', function() {
    $filename = 'laravel.png';
    $filePath = public_path($filename);
    $fileData = File::get($filePath);

    Storage::disk('google')->put($filename, $fileData);
    return 'File was saved to Google Drive';
});

Route::get('list-files', function() {
    $recursive = false; // Get subdirectories also?
    $contents = collect(Storage::disk('google')->listContents('/', $recursive));

    //return $contents->where('type', 'dir'); // directories
    return $contents->where('type', 'file')->mapWithKeys(fn (\League\Flysystem\StorageAttributes $file) =>
        [$file->path() => $file->extraMetadata()['name'] ?? $file->path()]
    );
});

Route::get('list-team-drives', function () {
    $service = Storage::disk('google')->getAdapter()->getService();
    $teamDrives = collect($service->teamdrives->listTeamdrives()->getTeamDrives());

    return $teamDrives->mapWithKeys(fn ($drive) =>
        [$drive->id => $drive->name]
    );
});

Route::get('get', function() {
    $filename = 'test.txt';
    $file = collect(Storage::disk('google')->listContents('/', false))
        ->first(fn (\League\Flysystem\StorageAttributes $file) =>
            $file->type() === 'file' && $file->extraMetadata()['name'] === $filename
        ); // There could be duplicate directory names!

    $rawData = Storage::disk('google')->get($file->path()); // raw content

    return response($rawData, 200)
        ->header('ContentType', $file->mimeType())
        ->header('Content-Disposition', "attachment; filename=$filename");
});

Route::get('put-get-stream', function() {
    // Use a stream to upload and download larger files
    // to avoid exceeding PHP's memory limit.

    // Thanks to @Arman8852's comment:
    // https://github.com/ivanvermeyen/laravel-google-drive-demo/issues/4#issuecomment-331625531
    // And this excellent explanation from Freek Van der Herten:
    // https://murze.be/2015/07/upload-large-files-to-s3-using-laravel-5/

    // Assume this is a large file...
    $filename = 'laravel.png';
    $filePath = public_path($filename);

    // Upload using a stream...
    Storage::disk('google')->put($filename, fopen($filePath, 'r+'));

    // Get file details...
    $file = collect(Storage::disk('google')->listContents('/', 'false'))
        ->first(fn (\League\Flysystem\StorageAttributes $file) =>
            $file->type() === 'file' && $file->extraMetadata()['name'] === $filename
        ); // There could be duplicate directory names!

    // Store the file locally...
    //$readStream = Storage::disk('google')->getDriver()->readStream($filename);
    //$targetFile = storage_path("downloaded-{$filename}");
    //file_put_contents($targetFile, stream_get_contents($readStream), FILE_APPEND);

    // Stream the file to the browser...
    $readStream = Storage::disk('google')->getDriver()->readStream($file->path());

    return response()->stream(function () use ($readStream) {
        fpassthru($readStream);
    }, 200, [
        'Content-Type' => $file->mimeType(),
        //'Content-disposition' => 'attachment; filename='.$filename, // force download?
    ]);
});

Route::get('create-dir', function() {
    Storage::disk('google')->makeDirectory('Test Dir');
    return 'Directory was created in Google Drive';
});

Route::get('create-sub-dir', function() {
    // Find parent dir for reference
    $dir = collect(Storage::disk('google')->listContents('/', false))
        ->first(fn (\League\Flysystem\StorageAttributes $file) =>
            $file->type() === 'dir' && $file->extraMetadata()['name'] === 'Test Dir'
        ); // There could be duplicate directory names!

    if (! $dir) {
        return 'Directory "Test Dir" does not exist!';
    }

    // Create sub dir
    Storage::disk('google')->makeDirectory($dir->path().'/Sub Dir');

    return 'Sub Directory was created in Google Drive';
});

Route::get('put-in-dir', function() {
    $dir = collect(Storage::disk('google')->listContents('/', false))
        ->first(fn (\League\Flysystem\StorageAttributes $file) =>
            $file->type() === 'dir' && $file->extraMetadata()['name'] === 'Test Dir'
        ); // There could be duplicate directory names!

    if (! $dir) {
        return 'Directory "Test Dir" does not exist!';
    }

    Storage::disk('google')->put($dir->path().'/test.txt', 'Hello World');

    return 'File was created in the sub directory in Google Drive';
});

Route::get('list-folder-contents', function() {
    // The human readable folder name to get the contents of...
    // For simplicity, this folder is assumed to exist in the root directory.
    $folder = 'Test Dir';
    $dir = collect(Storage::disk('google')->listContents('/', false))
        ->first(fn (\League\Flysystem\StorageAttributes $file) =>
            $file->type() === 'dir' && $file->extraMetadata()['name'] === $folder
        ); // There could be duplicate directory names!

    if (! $dir) {
        return 'Directory "'.$folder.'" does not exist!';
    }

    // Get directory contents...
    $contents = collect(Storage::disk('google')->listContents($dir->path(), false));

    return $contents->mapWithKeys(fn (\League\Flysystem\StorageAttributes $content) =>
        [$content->path() => $folder.'/'.($content->extraMetadata()['name'] ?? $content->path())]
    );
});

Route::get('newest', function() {
    $filename = 'test.txt';
    Storage::disk('google')->put($filename, \Carbon\Carbon::now()->toDateTimeString());

    $file = collect(Storage::disk('google')->listContents('/', false))
        ->filter(fn (\League\Flysystem\StorageAttributes $file) =>
            $file->type() === 'file' && $file->extraMetadata()['name'] === $filename
        )
        ->sortBy('lastModified')
        ->last();

    return Storage::disk('google')->get($file->path());
});

Route::get('delete', function() {
    $filename = 'test.txt';

    $file = collect(Storage::disk('google')->listContents('/', false))
        ->first(fn (\League\Flysystem\StorageAttributes $file) =>
            $file->type() === 'file' && $file->extraMetadata()['name'] === $filename
        ); // there can be duplicate file names!

    if (! $file) {
        return 'File ".$filename." does not exist!';
    }

    Storage::disk('google')->delete($file->path());

    return 'File was deleted from Google Drive';
});

Route::get('delete-dir', function() {
    $directoryName = 'Test Dir';

    $dir = collect(Storage::disk('google')->listContents('/', false))
        ->first(fn (\League\Flysystem\StorageAttributes $file) =>
            $file->type() === 'dir' && $file->extraMetadata()['name'] === $directoryName
        ); // there can be duplicate file names!

    if (! $dir) {
        return 'Directory "'.$directoryName.'" does not exist!';
    }

    Storage::disk('google')->deleteDirectory($dir->path());

    return 'Directory was deleted from Google Drive';
});

Route::get('rename-dir', function() {
    $directoryName = 'test';

    // First we need to create a directory to rename
    Storage::disk('google')->makeDirectory($directoryName);

    // Now find that directory and use its ID (path) to rename it
    $dir = collect(Storage::disk('google')->listContents('/', false))
        ->first(fn (\League\Flysystem\StorageAttributes $file) =>
            $file->type() === 'dir' && $file->extraMetadata()['name'] === $directoryName
        ); // there can be duplicate file names!

    if (! $dir) {
        return 'Directory "'.$directoryName.'" does not exist!';
    }

    Storage::disk('google')->move($dir->path(), 'new-test');

    return 'Directory was renamed in Google Drive';
});

Route::get('share', function() {
    $filename = 'test.txt';

    // Store a demo file
    Storage::disk('google')->put($filename, 'Hello World');

    // Get the file to find the ID
    $file = collect(Storage::disk('google')->listContents('/', false))
        ->first(fn (\League\Flysystem\StorageAttributes $file) =>
            $file->type() === 'file' && $file->extraMetadata()['name'] === $filename
        ); // there can be duplicate file names!

    // Change permissions
    // - https://developers.google.com/drive/v3/web/about-permissions
    // - https://developers.google.com/drive/v3/reference/permissions
    $service = Storage::disk('google')->getAdapter()->getService();
    $permission = new \Google_Service_Drive_Permission();
    $permission->setRole('reader');
    $permission->setType('anyone');
    $permission->setAllowFileDiscovery(false);
    $permissions = $service->permissions->create($file->path(), $permission);

    return Storage::disk('google')->url($file->path());
});

Route::get('export/{filename}', function ($filename) {
    $service = Storage::disk('google')->getAdapter()->getService();
    $file = Storage::disk('google')->getAdapter()->getMetadata($filename);

    $mimeType = 'application/pdf';
    $export = $service->files->export($file->path(), $mimeType);

    return response($export->getBody(), 200, [
        'Content-Type' => $mimeType,
        'Content-disposition' => 'attachment; filename='.$filename.'.pdf',
    ]);
});
