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
        [$file->path() => pathinfo($file->path(),PATHINFO_BASENAME)]
    );
});

Route::get('list-team-drives', function () {
    $service = Storage::disk('google')->getAdapter()->getService();
    $teamDrives = collect($service->teamdrives->listTeamdrives()->getTeamDrives());

    return $teamDrives->mapWithKeys(fn (\Google\Service\Drive\TeamDrive $drive) =>
        [$drive->id => $drive->name]
    );
});

Route::get('get', function() {
    // there can be duplicate file names!
    $filename = 'test.txt';

    $rawData = Storage::disk('google')->get($filename); // raw content
    $file = Storage::disk('google')->getAdapter()->getMetadata($filename); // array with file info

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
    $file = Storage::disk('google')->getAdapter()->getMetadata($filename); // array with file info

    // Store the file locally...
    //$readStream = Storage::disk('google')->getDriver()->readStream($filename);
    //$targetFile = storage_path("downloaded-{$filename}");
    //file_put_contents($targetFile, stream_get_contents($readStream), FILE_APPEND);

    // Stream the file to the browser...
    $readStream = Storage::disk('google')->getDriver()->readStream($filename);

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
    // Create parent dir
    Storage::disk('google')->makeDirectory('Test Dir/Sub Dir');
    return 'Sub Directory was created in Google Drive';
});

Route::get('put-in-dir', function() {
    Storage::disk('google')->put('Test Dir/test.txt', 'Hello World');
    return 'File was created in the sub directory in Google Drive';
});

Route::get('list-folder-contents', function() {
    // The human readable folder name to get the contents of...
    // For simplicity, this folder is assumed to exist in the root directory.
    $folder = 'Test Dir';

    // Get directory contents...
    $files = collect(Storage::disk('google')->listContents($folder, false));

    return $files->mapWithKeys(fn (\League\Flysystem\StorageAttributes $file) =>
        [$file->path() => pathinfo($file->path(),PATHINFO_BASENAME)]
    );
});

Route::get('delete', function() {
    $path = 'Test Dir/test.txt';
    Storage::disk('google')->put($path, 'Hello World');
    Storage::disk('google')->delete($path);
    return 'File was deleted from Google Drive';
});

Route::get('delete-dir', function() {
    $directoryName = 'Test Dir';

    // First we need to create a directory to delete
    Storage::disk('google')->makeDirectory($directoryName);
    Storage::disk('google')->deleteDirectory($directoryName);

    return 'Directory was deleted from Google Drive';
});

Route::get('rename-dir', function() {
    $directoryName = 'test';

    Storage::disk('google')->makeDirectory($directoryName);
    Storage::disk('google')->move($directoryName, 'new-test');

    return 'Directory was renamed in Google Drive';
});

Route::get('share', function() {
    $filename = 'test.txt';
    // Store a demo file with public permission
    Storage::disk('google')->put($filename, 'Hello World', 'public');
    return Storage::disk('google')->url($filename);
});

Route::get('export/{filename}', function ($filename) {
    $service = Storage::disk('google')->getAdapter()->getService();
    $file = Storage::disk('google')->getAdapter()->getMetadata($filename);

    $mimeType = 'application/pdf';
    $export = $service->files->export($file->extraMetadata()['id'], $mimeType);

    return response($export->getBody(), 200, [
        'Content-Type' => $mimeType,
        'Content-disposition' => 'attachment; filename='.$filename.'.pdf',
    ]);
});
