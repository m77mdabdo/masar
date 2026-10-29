<?php

declare(strict_types=1);

use App\Rules\SafeImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

/*
 * Uploads are judged by their bytes, not their names, and are rebuilt from
 * pixels before anything stores them (CLAUDE.md §5).
 */
function upload(string $bytes, string $name): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'up');
    file_put_contents($path, $bytes);

    return new UploadedFile($path, $name, null, null, true);
}

function jpegBytes(int $w = 40, int $h = 30): string
{
    $im = imagecreatetruecolor($w, $h);
    imagefill($im, 0, 0, imagecolorallocate($im, 20, 80, 60));
    ob_start();
    imagejpeg($im);

    return (string) ob_get_clean();
}

function passes(UploadedFile $file): bool
{
    return Validator::make(['f' => $file], ['f' => [new SafeImage]])->passes();
}

it('accepts a real JPEG, PNG and WebP', function (string $fn, string $name): void {
    $im = imagecreatetruecolor(20, 20);
    ob_start();
    $fn($im);

    expect(passes(upload((string) ob_get_clean(), $name)))->toBeTrue();
})->with([['imagejpeg', 'a.jpg'], ['imagepng', 'a.png'], ['imagewebp', 'a.webp']]);

it('refuses a script named like a photograph', function (): void {
    expect(passes(upload("<?php system(\$_GET['c']); ?>", 'photo.jpg')))->toBeFalse();
});

it('refuses SVG, GIF and an executable whatever the extension', function (string $bytes, string $name): void {
    expect(passes(upload($bytes, $name)))->toBeFalse();
})->with([
    ['<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>', 'x.png'],
    ["GIF89a\x01\x00\x01\x00\x00\x00\x00", 'x.gif'],
    ["MZ\x90\x00\x03\x00\x00\x00", 'x.jpg'],
    ["\x7FELF\x02\x01\x01", 'x.webp'],
]);

it('strips a payload appended to a real image by re-encoding it', function (): void {
    $file = upload(jpegBytes()."<?php echo 'pwned'; ?>", 'polyglot.jpg');

    expect(passes($file))->toBeTrue();

    $stored = (string) file_get_contents($file->getRealPath());
    expect($stored)->not->toContain('<?php')->and(str_starts_with($stored, "\xFF\xD8\xFF"))->toBeTrue()
        ->and(getimagesize($file->getRealPath())[0])->toBe(40);
});

it('refuses an image past the size or dimension limits', function (): void {
    config()->set('masar.media.max_dimension', 30);
    expect(passes(upload(jpegBytes(40, 20), 'wide.jpg')))->toBeFalse();

    config()->set('masar.media.max_dimension', 8000);
    config()->set('masar.media.max_upload_kb', 0);
    expect(passes(upload(jpegBytes(), 'big.jpg')))->toBeFalse();
});

it('guards every image upload field in the admin', function (): void {
    // A new upload field without the rule fails here, not in production.
    $files = ['Resources/Articles/Schemas/ArticleBlocks.php', 'Resources/Articles/Schemas/ArticleForm.php',
        'Resources/People/PersonResource.php', 'Resources/Companies/CompanyResource.php', 'Pages/ManageSettings.php'];
    $uploads = 0;
    $guarded = 0;

    foreach (\Symfony\Component\Finder\Finder::create()->files()->in(app_path('Filament'))->name('*.php') as $file) {
        $src = $file->getContents();
        $uploads += preg_match_all('/FileUpload::make\(/', $src);
        $guarded += substr_count($src, 'new \App\Rules\SafeImage');
    }

    expect($uploads)->toBe(7)->and($guarded)->toBe($uploads);
});
