<?php

namespace Tests\Feature;

use App\Models\Tutorial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TutorialApiTest extends TestCase
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
        // Clean up test files from uploads/tutorials directory
        $uploadDir = public_path('uploads/tutorials');
        if (File::isDirectory($uploadDir)) {
            $files = File::files($uploadDir);
            foreach ($files as $file) {
                if (str_starts_with($file->getFilename(), 'test_') || preg_match('/^\d+_[a-zA-Z0-9]+\.(mp4|webm)$/', $file->getFilename())) {
                    File::delete($file->getPathname());
                }
            }
        }

        parent::tearDown();
    }

    /**
     * 1. Schema & Migration Verification
     */
    public function test_tutorials_table_exists_and_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('tutorials'), 'Table tutorials should exist');
        $this->assertTrue(Schema::hasColumns('tutorials', [
            'id',
            'title',
            'description',
            'video_type',
            'video_source',
            'created_at',
            'updated_at',
        ]), 'Table tutorials should have all required columns');
    }

    /**
     * 2. Eloquent Model Tests
     */
    public function test_tutorial_model_fillable_and_appended_video_url(): void
    {
        $tutorialFile = Tutorial::create([
            'title' => 'Panduan File',
            'description' => 'Deskripsi panduan',
            'video_type' => 'file',
            'video_source' => '/uploads/tutorials/sample.mp4',
        ]);

        $this->assertEquals('Panduan File', $tutorialFile->title);
        $this->assertEquals('file', $tutorialFile->video_type);
        $this->assertStringContainsString('/uploads/tutorials/sample.mp4', $tutorialFile->video_url);

        $tutorialYoutube = Tutorial::create([
            'title' => 'Panduan YouTube',
            'description' => 'Deskripsi YouTube',
            'video_type' => 'youtube',
            'video_source' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
        ]);

        $this->assertEquals('https://www.youtube.com/embed/dQw4w9WgXcQ', $tutorialYoutube->video_url);
    }

    /**
     * 3. RBAC & Authentication Protection
     */
    public function test_guest_cannot_access_tutorials_endpoints(): void
    {
        $this->getJson('/api/admin/tutorials')->assertStatus(401);
        $this->postJson('/api/admin/tutorials', ['title' => 'Sample'])->assertStatus(401);
        $this->deleteJson('/api/admin/tutorials/1')->assertStatus(401);
    }

    public function test_non_admin_cannot_access_tutorials_endpoints(): void
    {
        $this->actingAs($this->nonAdminUser)
            ->getJson('/api/admin/tutorials')
            ->assertStatus(403);

        $this->actingAs($this->nonAdminUser)
            ->postJson('/api/admin/tutorials', ['title' => 'Sample'])
            ->assertStatus(403);
    }

    /**
     * 4. Index Listing Endpoint
     */
    public function test_admin_can_list_tutorials_in_latest_order(): void
    {
        $t1 = Tutorial::create([
            'title' => 'First Tutorial',
            'video_type' => 'link',
            'video_source' => 'https://example.com/1',
            'created_at' => now()->subDay(),
        ]);

        $t2 = Tutorial::create([
            'title' => 'Second Tutorial',
            'video_type' => 'link',
            'video_source' => 'https://example.com/2',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/tutorials');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $t2->id)
            ->assertJsonPath('data.1.id', $t1->id);
    }

    /**
     * 5. Validation Tests (Mutual Exclusivity, Required Fields, File Constraints)
     */
    public function test_store_fails_when_title_is_missing(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/tutorials', [
                'video_link' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_store_fails_when_neither_file_nor_link_is_provided(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/tutorials', [
                'title' => 'Tutorial Tanpa Media',
                'description' => 'Deskripsi saja',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_store_fails_when_both_file_and_link_are_provided(): void
    {
        $fakeVideo = UploadedFile::fake()->create('test_video.mp4', 1000, 'video/mp4');

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/tutorials', [
                'title' => 'Tutorial Ganda',
                'video_file' => $fakeVideo,
                'video_link' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_store_fails_with_invalid_file_mime_type(): void
    {
        $fakePdf = UploadedFile::fake()->create('document.pdf', 1000, 'application/pdf');

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/tutorials', [
                'title' => 'PDF Upload Attempt',
                'video_file' => $fakePdf,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['video_file']);
    }

    public function test_store_fails_when_file_exceeds_50mb(): void
    {
        // 55MB = 56320 KB (max rule is 51200 KB)
        $oversizedVideo = UploadedFile::fake()->create('oversized.mp4', 55000, 'video/mp4');

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/tutorials', [
                'title' => 'Oversized Video',
                'video_file' => $oversizedVideo,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['video_file']);
    }

    /**
     * 6. Video Upload (MP4 & WebM) Handling
     */
    public function test_admin_can_upload_mp4_video_successfully(): void
    {
        $fakeVideo = UploadedFile::fake()->create('tutorial_guide.mp4', 4000, 'video/mp4');

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/tutorials', [
                'title' => 'Tutorial MP4 Resmi',
                'description' => 'Panduan pendaftaran magang format MP4',
                'video_file' => $fakeVideo,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Tutorial MP4 Resmi')
            ->assertJsonPath('data.description', 'Panduan pendaftaran magang format MP4')
            ->assertJsonPath('data.video_type', 'file');

        $videoSource = $response->json('data.video_source');
        $this->assertStringStartsWith('/uploads/tutorials/', $videoSource);

        $physicalPath = public_path(ltrim($videoSource, '/'));
        $this->assertTrue(File::exists($physicalPath), 'Physical MP4 file must exist in public/uploads/tutorials');

        // Cleanup
        if (File::exists($physicalPath)) {
            File::delete($physicalPath);
        }
    }

    public function test_admin_can_upload_webm_video_successfully(): void
    {
        $fakeVideo = UploadedFile::fake()->create('tutorial_guide.webm', 2500, 'video/webm');

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/tutorials', [
                'title' => 'Tutorial WebM',
                'video_file' => $fakeVideo,
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

    /**
     * 7. YouTube URL Conversion Tests (watch, youtu.be, shorts, live, embed)
     */
    public function test_youtube_standard_watch_url_conversion(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/tutorials', [
                'title' => 'YouTube Watch',
                'video_link' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.video_type', 'youtube')
            ->assertJsonPath('data.video_source', 'https://www.youtube.com/embed/dQw4w9WgXcQ');
    }

    public function test_youtube_watch_with_extra_query_params_conversion(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/tutorials', [
                'title' => 'YouTube Watch Extra Params',
                'video_link' => 'https://www.youtube.com/watch?app=desktop&v=dQw4w9WgXcQ&t=45s',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.video_type', 'youtube')
            ->assertJsonPath('data.video_source', 'https://www.youtube.com/embed/dQw4w9WgXcQ');
    }

    public function test_youtube_shortened_youtu_be_url_conversion(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/tutorials', [
                'title' => 'YouTube Shortened Link',
                'video_link' => 'https://youtu.be/dQw4w9WgXcQ?t=15',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.video_type', 'youtube')
            ->assertJsonPath('data.video_source', 'https://www.youtube.com/embed/dQw4w9WgXcQ');
    }

    public function test_youtube_shorts_url_conversion(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/tutorials', [
                'title' => 'YouTube Shorts',
                'video_link' => 'https://www.youtube.com/shorts/dQw4w9WgXcQ',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.video_type', 'youtube')
            ->assertJsonPath('data.video_source', 'https://www.youtube.com/embed/dQw4w9WgXcQ');
    }

    public function test_youtube_live_url_conversion(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/tutorials', [
                'title' => 'YouTube Live',
                'video_link' => 'https://www.youtube.com/live/dQw4w9WgXcQ',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.video_type', 'youtube')
            ->assertJsonPath('data.video_source', 'https://www.youtube.com/embed/dQw4w9WgXcQ');
    }

    public function test_youtube_existing_embed_url(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/tutorials', [
                'title' => 'YouTube Embed Existing',
                'video_link' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.video_type', 'youtube')
            ->assertJsonPath('data.video_source', 'https://www.youtube.com/embed/dQw4w9WgXcQ');
    }

    /**
     * 8. Google Drive URL Conversion Tests
     */
    public function test_google_drive_view_sharing_url_conversion(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/tutorials', [
                'title' => 'GDrive View Link',
                'video_link' => 'https://drive.google.com/file/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/view?usp=sharing',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.video_type', 'gdrive')
            ->assertJsonPath('data.video_source', 'https://drive.google.com/file/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/preview');
    }

    public function test_google_drive_open_id_url_conversion(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/tutorials', [
                'title' => 'GDrive Open Link',
                'video_link' => 'https://drive.google.com/open?id=1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.video_type', 'gdrive')
            ->assertJsonPath('data.video_source', 'https://drive.google.com/file/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/preview');
    }

    public function test_google_drive_uc_id_url_conversion(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/tutorials', [
                'title' => 'GDrive UC Link',
                'video_link' => 'https://drive.google.com/uc?id=1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms&export=download',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.video_type', 'gdrive')
            ->assertJsonPath('data.video_source', 'https://drive.google.com/file/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/preview');
    }

    /**
     * 9. Generic Link URL Fallback
     */
    public function test_generic_video_url_fallback(): void
    {
        $genericUrl = 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4';

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/tutorials', [
                'title' => 'Generic Video Link',
                'video_link' => $genericUrl,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.video_type', 'link')
            ->assertJsonPath('data.video_source', $genericUrl);
    }

    /**
     * 10. Deletion and Physical File Unlinking Tests
     */
    public function test_destroy_deletes_database_record_and_physical_mp4_file(): void
    {
        $fakeVideo = UploadedFile::fake()->create('delete_me.mp4', 1500, 'video/mp4');

        $createRes = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/tutorials', [
                'title' => 'Video Akan Dihapus',
                'video_file' => $fakeVideo,
            ]);

        $tutorialId = $createRes->json('data.id');
        $videoSource = $createRes->json('data.video_source');
        $physicalPath = public_path(ltrim($videoSource, '/'));

        $this->assertTrue(File::exists($physicalPath), 'Physical file should exist before deletion');

        $deleteRes = $this->actingAs($this->adminUser)
            ->deleteJson("/api/admin/tutorials/{$tutorialId}");

        $deleteRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Tutorial berhasil dihapus.');

        $this->assertDatabaseMissing('tutorials', ['id' => $tutorialId]);
        $this->assertFalse(File::exists($physicalPath), 'Physical file should be unlinked/deleted from disk');
    }

    public function test_destroy_link_tutorial_deletes_record_without_file_error(): void
    {
        $tutorial = Tutorial::create([
            'title' => 'Tutorial YouTube Delete',
            'video_type' => 'youtube',
            'video_source' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
        ]);

        $deleteRes = $this->actingAs($this->adminUser)
            ->deleteJson("/api/admin/tutorials/{$tutorial->id}");

        $deleteRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('tutorials', ['id' => $tutorial->id]);
    }
}
