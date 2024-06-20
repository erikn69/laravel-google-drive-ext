# Laravel & Google Drive Storage

#### Demo project with Laravel 11.x

Look at the commit history to see each of the steps I have taken to set this up.

## Set up this demo project locally

```
git clone git@github.com:erikn69/laravel-google-drive-demo.git
git checkout 11.x
composer install
cp .env.example .env
php artisan key:generate
```

### I took care of this:

This will also install only [one additional package](https://github.com/masbug/flysystem-google-drive-ext) that is not included by Laravel out of the box:

```
"masbug/flysystem-google-drive-ext":"^2.0"
```

I have included [GoogleDriveServiceProvider](app/Providers/GoogleDriveServiceProvider.php) which I have added to the `providers` array in [`bootstrap/providers.php`](bootstrap/providers.php#L5), and added a `google` disk in [`config/filesystems.php`](config/filesystems.php#L59-L66):

```php
'disks' => [

    // ...

    'google' => [
        'driver' => 'google',
        'clientId' => env('GOOGLE_DRIVE_CLIENT_ID'),
        'clientSecret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
        'refreshToken' => env('GOOGLE_DRIVE_REFRESH_TOKEN'),
        'folder' => env('GOOGLE_DRIVE_FOLDER'),
        // 'teamDriveId' => env('GOOGLE_DRIVE_TEAM_DRIVE_ID'),
    ],

    // ...

],
```

## Create your Google Drive API keys

Detailed information on how to obtain your API ID, secret and refresh token:

-   [Getting your Client ID and Secret](README/1-getting-your-dlient-id-and-secret.md)
-   [Getting your Refresh Token](README/2-getting-your-refresh-token.md)
-   [Getting your Team Folder ID](README/3-getting-your-team-folder-id.md)

## Update `.env` file

Add the keys you created to your `.env` file and set `google` as your default cloud storage. You can copy the [`.env.example`](.env.example#L66-L71) file and fill in the blanks.

```
FILESYSTEM_DRIVER=google
GOOGLE_DRIVE_CLIENT_ID=xxx.apps.googleusercontent.com
GOOGLE_DRIVE_CLIENT_SECRET=xxx
GOOGLE_DRIVE_REFRESH_TOKEN=xxx
GOOGLE_DRIVE_FOLDER=folder_name
#GOOGLE_DRIVE_TEAM_DRIVE_ID=xxx
```

## Using multiple Google Drive accounts

If you want to use multiple Google Drive accounts in a single Laravel app, you need to get the API keys for each one as described above and store them separately in your `.env` file:

```
MAIN_GOOGLE_DRIVE_CLIENT_ID=xxx.apps.googleusercontent.com
MAIN_GOOGLE_DRIVE_CLIENT_SECRET=xxx
MAIN_GOOGLE_DRIVE_REFRESH_TOKEN=xxx
MAIN_GOOGLE_DRIVE_FOLDER=

BACKUP_GOOGLE_DRIVE_CLIENT_ID=xxx.apps.googleusercontent.com
BACKUP_GOOGLE_DRIVE_CLIENT_SECRET=xxx
BACKUP_GOOGLE_DRIVE_REFRESH_TOKEN=xxx
BACKUP_GOOGLE_DRIVE_FOLDER=
```

Then you should add a disk in `config/filesystems.php` for each account using the `google` driver and the account specific keys:

```php
'main_google' => [
    'driver' => 'google',
    'clientId' => env('MAIN_GOOGLE_DRIVE_CLIENT_ID'),
    'clientSecret' => env('MAIN_GOOGLE_DRIVE_CLIENT_SECRET'),
    'refreshToken' => env('MAIN_GOOGLE_DRIVE_REFRESH_TOKEN'),
    'folder' => env('MAIN_GOOGLE_DRIVE_FOLDER'),
],

'backup_google' => [
    'driver' => 'google',
    'clientId' => env('BACKUP_GOOGLE_DRIVE_CLIENT_ID'),
    'clientSecret' => env('BACKUP_GOOGLE_DRIVE_CLIENT_SECRET'),
    'refreshToken' => env('BACKUP_GOOGLE_DRIVE_REFRESH_TOKEN'),
    'folder' => env('BACKUP_GOOGLE_DRIVE_FOLDER'),
],
```

Now you can access the drives like so:

```php
$mainDisk = Storage::disk('main_google');
$backupDisk = Storage::disk('backup_google');
```

Keep in mind that there can only be one default cloud storage drive, defined by `FILESYSTEM_DRIVER` in your `.env` (or config) file. If you set it to `main_google`, that will be the cloud drive:

```php
Storage::cloud(); // refers to Storage::disk('main_google')
```

## Available routes

| Route                 | Description                              |
| --------------------- | ---------------------------------------- |
| /put                  | Puts a new `test.txt` file to Google Drive |
| /put-existing         | Puts an existing file to Google Drive    |
| /list-files           | Lists all files in Google Drive (root directory, not recursive by default)|
| /list-folder-contents | List all files in a specific folder(`Test Dir`)|
| /list-team-drives     | Lists all team drives in Google Drive |
| /get                  | Finds and downloads the `test.txt` file from Google Drive |
| /create-dir           | Creates a `Test Dir` directory   |
| /create-sub-dir       | Creates a `Test Dir` directory and a `Sub Dir` sub directory |
| /put-in-dir           | Puts a new `test.txt` file to the `Test Dir` sub directory |
| /delete               | Delete a file from Google Drive          |
| /delete-dir           | Delete an entire directory from Google Drive |
| /rename-dir           | Rename a directory in Google Drive       |
| /put-get-stream       | Use a stream to store and get larger files |
| /share                | Change permissions to share a file with the public |
| /export/{filename}    | Export a Google doc (to PDF) |

This is a very basic example to get you started. To see the logic behind these routes, check [`routes/web.php`](routes/web.php).

