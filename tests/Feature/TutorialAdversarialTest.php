<?php

namespace Tests\Feature;

use App\Models\Tutorial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TutorialAdversarialTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $nonAdminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'is_admin' => true,
        ]);

        $this->nonAdminUser = User::factory()->create([
            'is_admin' => false,
        ]);
    }

    protected function tearDown(): void
    {
        $uploadDir = public_path('uploads/tutorials');
        if (File::isDirectory($uploadDir)) {
            $files = File::files($uploadDir);
            foreach ($files as $file) {
                if (str_starts_with($file->getFilename(), 'adv_') || preg_match('/^\d+_[a-zA-Z0-9]+\.(mp4|webm)$/', $file->getFilename())) {
                    File::delete($file->getPathname());
                }
            }
        }

        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | 1. URL Edge Cases Stress Testing
    |--------------------------------------------------------------------------
    */

    #[DataProvider('youtubeUrlProvider')]
    public function test_youtube_url_edge_cases_transformed_to_embed(string $inputUrl, string $expectedEmbedUrl): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/tutorials', [
                'title' => 'YouTube Test ' . substr(md5($inputUrl), 0, 6),
                'video_link' => $inputUrl,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.video_type', 'youtube')
            ->assertJsonPath('data.video_source', $expectedEmbedUrl);
    }

    public static function youtubeUrlProvider(): array
    {
        return [
            'Query param ordering' => [
                'https://www.youtube.com/watch?feature=shared&v=dQw4w9WgXcQ',
                'https://www.youtube.com/embed/dQw4w9WgXcQ',
            ],
            'Shortened youtu.be' => [
                'https://youtu.be/dQw4w9WgXcQ',
                'https://www.youtube.com/embed/dQw4w9WgXcQ',
            ],
            'Mobile URL' => [
                'https://m.youtube.com/watch?v=dQw4w9WgXcQ',
                'https://www.youtube.com/embed/dQw4w9WgXcQ',
            ],
            'Shorts URL' => [
                'https://www.youtube.com/shorts/dQw4w9WgXcQ',
                'https://www.youtube.com/embed/dQw4w9WgXcQ',
            ],
            'Live URL' => [
                'https://www.youtube.com/live/dQw4w9WgXcQ',
                'https://www.youtube.com/embed/dQw4w9WgXcQ',
            ],
            'Existing Embed URL' => [
                'https://www.youtube.com/embed/dQw4w9WgXcQ',
                'https://www.youtube.com/embed/dQw4w9WgXcQ',
            ],
            'With timestamp and list' => [
                'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42s&list=PL123456789',
                'https://www.youtube.com/embed/dQw4w9WgXcQ',
            ],
            'Shortened with tracking param' => [
                'https://youtu.be/dQw4w9WgXcQ?si=abc123xyz_987',
                'https://www.youtube.com/embed/dQw4w9WgXcQ',
            ],
            'Legacy v/ link' => [
                'https://www.youtube.com/v/dQw4w9WgXcQ',
                'https://www.youtube.com/embed/dQw4w9WgXcQ',
            ],
        ];
    }

    #[DataProvider('googleDriveUrlProvider')]
    public function test_google_drive_url_edge_cases_transformed_to_preview(string $inputUrl, string $expectedPreviewUrl): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/tutorials', [
                'title' => 'GDrive Test ' . substr(md5($inputUrl), 0, 6),
                'video_link' => $inputUrl,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.video_type', 'gdrive')
            ->assertJsonPath('data.video_source', $expectedPreviewUrl);
    }

    public static function googleDriveUrlProvider(): array
    {
        $expected = 'https://drive.google.com/file/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/preview';

        return [
            'file/d/view?usp=sharing' => [
                'https://drive.google.com/file/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/view?usp=sharing',
                $expected,
            ],
            'open?id=' => [
                'https://drive.google.com/open?id=1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms',
                $expected,
            ],
            'uc?id=' => [
                'https://drive.google.com/uc?id=1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms',
                $expected,
            ],
            'file/d/preview direct' => [
                'https://drive.google.com/file/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/preview',
                $expected,
            ],
            'uc?export=download&id=' => [
                'https://drive.google.com/uc?export=download&id=1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms',
                $expected,
            ],
        ];
    }

    public function test_general_mp4_url_fallback(): void
    {
        $url = 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4';

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/tutorials', [
                'title' => 'Big Buck Bunny Direct MP4 Link',
                'video_link' => $url,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.video_type', 'link')
            ->assertJsonPath('data.video_source', $url);
    }

    public function test_non_youtube_non_gdrive_url_fallback(): void
    {
        $url = 'https://vimeo.com/76979871';

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/tutorials', [
                'title' => 'Vimeo Video Link',
                'video_link' => $url,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.video_type', 'link')
            ->assertJsonPath('data.video_source', $url);
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Boundary & Validation Attacks
    |--------------------------------------------------------------------------
    */

    public function test_rejects_empty_and_whitespace_title(): void
    {
        // Empty title
        $res1 = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/tutorials', [
                'title' => '',
                'video_link' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ]);
        $res1->assertStatus(422)->assertJsonValidationErrors(['title']);

        // Whitespace only title
        $res2 = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/tutorials', [
                'title' => '   ',
                'video_link' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ]);
        $res2->assertStatus(422)->assertJsonValidationErrors(['title']);
    }

    public function test_rejects_title_exceeding_255_chars(): void
    {
        $longTitle = str_repeat('A', 256);

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/tutorials', [
                'title' => $longTitle,
                'video_link' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_accepts_title_exactly_255_chars(): void
    {
        $maxTitle = str_repeat('A', 255);

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/tutorials', [
                'title' => $maxTitle,
                'video_link' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', $maxTitle);
    }

    public function test_strict_rejection_when_both_file_and_link_provided(): void
    {
        $fakeVideo = UploadedFile::fake()->create('dual_media.mp4', 1024, 'video/mp4');

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/tutorials', [
                'title' => 'Dual Media Attack',
                'video_file' => $fakeVideo,
                'video_link' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_strict_rejection_when_neither_file_nor_link_provided(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/tutorials', [
                'title' => 'No Media Attack',
                'description' => 'Just text description',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_strict_rejection_when_video_link_is_only_whitespace(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/tutorials', [
                'title' => 'Whitespace Link Attack',
                'video_link' => '   ',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    #[DataProvider('disallowedFileTypesProvider')]
    public function test_strict_rejection_of_disallowed_file_types(string $filename, string $mimeType): void
    {
        $fakeFile = UploadedFile::fake()->create($filename, 500, $mimeType);

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/tutorials', [
                'title' => "Disallowed File: {$filename}",
                'video_file' => $fakeFile,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['video_file']);
    }

    public static function disallowedFileTypesProvider(): array
    {
        return [
            'PHP Script' => ['exploit.php', 'text/x-php'],
            'Executable' => ['malware.exe', 'application/x-msdownload'],
            'PDF Document' => ['contract.pdf', 'application/pdf'],
            'HTML Webpage' => ['xss.html', 'text/html'],
            'JPEG Image' => ['picture.jpg', 'image/jpeg'],
            'PNG Image' => ['image.png', 'image/png'],
            'Shell Script' => ['script.sh', 'application/x-sh'],
            'JavaScript' => ['payload.js', 'application/javascript'],
        ];
    }

    public function test_strict_rejection_of_file_exceeding_50mb(): void
    {
        // 52 MB = 53248 KB
        $oversizedVideo = UploadedFile::fake()->create('large_52mb.mp4', 53248, 'video/mp4');

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/tutorials', [
                'title' => '52MB Video Upload',
                'video_file' => $oversizedVideo,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['video_file']);
    }

    public function test_accepts_file_within_50mb_limit(): void
    {
        // 10 MB = 10240 KB
        $validVideo = UploadedFile::fake()->create('valid_10mb.mp4', 10240, 'video/mp4');

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/tutorials', [
                'title' => '10MB Video Upload',
                'video_file' => $validVideo,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.video_type', 'file');

        $videoSource = $response->json('data.video_source');
        $physicalPath = public_path(ltrim($videoSource, '/'));
        $this->assertTrue(File::exists($physicalPath));

        // Cleanup
        if (File::exists($physicalPath)) {
            File::delete($physicalPath);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Storage & Physical File Deletion
    |--------------------------------------------------------------------------
    */

    public function test_upload_creates_physical_file_in_uploads_tutorials(): void
    {
        $fakeVideo = UploadedFile::fake()->create('adv_sample.mp4', 2048, 'video/mp4');

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/tutorials', [
                'title' => 'Empirical Physical File Test',
                'description' => 'Test description',
                'video_file' => $fakeVideo,
            ]);

        $response->assertStatus(201);
        $videoSource = $response->json('data.video_source');
        $this->assertStringStartsWith('/uploads/tutorials/', $videoSource);

        $physicalPath = public_path(ltrim($videoSource, '/'));
        $this->assertTrue(File::exists($physicalPath), "Expected physical file at: {$physicalPath}");

        // Cleanup
        if (File::exists($physicalPath)) {
            File::delete($physicalPath);
        }
    }

    public function test_delete_endpoint_removes_db_record_and_unlinks_physical_file(): void
    {
        $fakeVideo = UploadedFile::fake()->create('adv_delete_target.mp4', 1024, 'video/mp4');

        $createRes = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/tutorials', [
                'title' => 'Video for Unlink Test',
                'video_file' => $fakeVideo,
            ]);

        $tutorialId = $createRes->json('data.id');
        $videoSource = $createRes->json('data.video_source');
        $physicalPath = public_path(ltrim($videoSource, '/'));

        $this->assertTrue(File::exists($physicalPath), 'Physical file must exist prior to deletion');

        $deleteRes = $this->actingAs($this->adminUser)
            ->deleteJson("/api/admin/tutorials/{$tutorialId}");

        $deleteRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Tutorial berhasil dihapus.');

        $this->assertDatabaseMissing('tutorials', ['id' => $tutorialId]);
        $this->assertFalse(File::exists($physicalPath), 'Physical file must be unlinked from disk');
    }

    public function test_delete_link_tutorial_succeeds_without_unlinking_errors(): void
    {
        $tutorial = Tutorial::create([
            'title' => 'YouTube Link Tutorial',
            'video_type' => 'youtube',
            'video_source' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
        ]);

        $deleteRes = $this->actingAs($this->adminUser)
            ->deleteJson("/api/admin/tutorials/{$tutorial->id}");

        $deleteRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('tutorials', ['id' => $tutorial->id]);
    }

    public function test_delete_tutorial_when_physical_file_was_manually_removed(): void
    {
        // Simulate orphaned record where physical file was deleted externally
        $tutorial = Tutorial::create([
            'title' => 'Orphaned Video Record',
            'video_type' => 'file',
            'video_source' => '/uploads/tutorials/non_existent_file_9999.mp4',
        ]);

        $deleteRes = $this->actingAs($this->adminUser)
            ->deleteJson("/api/admin/tutorials/{$tutorial->id}");

        $deleteRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('tutorials', ['id' => $tutorial->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | 4. Security & RBAC
    |--------------------------------------------------------------------------
    */

    public function test_unauthenticated_requests_return_401(): void
    {
        $this->getJson('/api/admin/tutorials')->assertStatus(401);
        $this->postJson('/api/admin/tutorials', ['title' => 'Unauthenticated Title'])->assertStatus(401);
        $this->deleteJson('/api/admin/tutorials/999')->assertStatus(401);
    }

    public function test_non_admin_requests_return_403(): void
    {
        $this->actingAs($this->nonAdminUser)
            ->getJson('/api/admin/tutorials')
            ->assertStatus(403);

        $this->actingAs($this->nonAdminUser)
            ->postJson('/api/admin/tutorials', [
                'title' => 'Non Admin Attack',
                'video_link' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ])
            ->assertStatus(403);

        $tutorial = Tutorial::create([
            'title' => 'Protected Tutorial',
            'video_type' => 'youtube',
            'video_source' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
        ]);

        $this->actingAs($this->nonAdminUser)
            ->deleteJson("/api/admin/tutorials/{$tutorial->id}")
            ->assertStatus(403);
    }

    public function test_xss_and_sql_injection_payload_in_title_and_description(): void
    {
        $xssTitle = '<script>alert("XSS")</script>';
        $xssDescription = '"><svg onload=alert(1)>';

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/tutorials', [
                'title' => $xssTitle,
                'description' => $xssDescription,
                'video_link' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', $xssTitle)
            ->assertJsonPath('data.description', $xssDescription);

        $this->assertDatabaseHas('tutorials', [
            'title' => $xssTitle,
            'description' => $xssDescription,
        ]);
    }
}
