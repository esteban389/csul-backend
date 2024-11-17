<?php

use App\DTOs\Auth\UserRole;
use App\Models\Process;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

test('anyone can get all processes', function () {

    Process::factory()->count(5)->create();

    $response = $this->get('/api/processes');

    $response->assertStatus(Response::HTTP_OK);
    $response->assertJsonCount(5);
});

test('anyone can get a process by id', function () {

    $process = Process::factory()->create();

    $response = $this->get("/api/processes/{$process->id}");

    $response->assertStatus(Response::HTTP_OK);
    $response->assertJson($process->toArray());
});

test('National Coordinator can create a process', function () {

    Storage::fake('public');
    $user = User::factory()->withRole(UserRole::NationalCoordinator)->create();
    $this->actingAs($user);
    $icon = UploadedFile::fake()->image('icon.png');

    $response = $this->post('/api/processes', [
        'name' => 'Process Name',
        'icon' => $icon,
    ]);

    $response->assertStatus(Response::HTTP_CREATED);
    $this->assertDatabaseHas('processes', [
        'name' => 'Process Name',
    ]);
    Storage::disk('public')->assertExists('/icons/' . $icon->hashName());
});

test('Other users cannot create a process', function () {

    $user = User::factory()->create();
    $this->actingAs($user);
    $icon = UploadedFile::fake()->image('icon.png');

    $response = $this->post('/api/processes', [
        'name' => 'Process Name',
        'icon' => $icon,
    ]);

    $response->assertStatus(Response::HTTP_FORBIDDEN);
    $this->assertDatabaseCount('processes', 0);
});

test('National Coordinator can update a process', function () {

    Storage::fake('public');
    $user = User::factory()->withRole(UserRole::NationalCoordinator)->create();
    $this->actingAs($user);
    $process = Process::factory()->create();
    $icon = UploadedFile::fake()->image('icon.png');

    $response = $this->put("/api/processes/{$process->id}", [
        'name' => 'Updated Process Name',
        'icon' => $icon,
    ]);

    $response->assertStatus(Response::HTTP_NO_CONTENT);
    $this->assertDatabaseHas('processes', [
        'name' => 'Updated Process Name',
    ]);
    Storage::disk('public')->assertExists(Process::query()->first()->icon);
});

test('Old icon is deleted when updating a process', function () {

    Storage::fake('public');
    $user = User::factory()->withRole(UserRole::NationalCoordinator)->create();
    $this->actingAs($user);
    $process = Process::factory()->create();
    $icon = UploadedFile::fake()->image('icon.png');
    $oldIcon = $process->icon;
    $process->update(['icon' => $oldIcon]);

    $response = $this->put("/api/processes/{$process->id}", [
        'name' => 'Updated Process Name',
        'icon' => $icon,
    ]);

    $response->assertStatus(Response::HTTP_NO_CONTENT);
    $this->assertDatabaseHas('processes', [
        'name' => 'Updated Process Name',
    ]);
    Storage::disk('public')->assertExists(Process::query()->first()->icon);
    Storage::disk('public')->assertMissing($oldIcon);
});

test('Other users cannot update a process', function () {

    $user = User::factory()->create();
    $this->actingAs($user);
    $process = Process::factory()->create();
    $icon = UploadedFile::fake()->image('icon.png');

    $response = $this->put("/api/processes/{$process->id}", [
        'name' => 'Updated Process Name',
        'icon' => $icon,
    ]);

    $response->assertStatus(Response::HTTP_FORBIDDEN);
    $this->assertDatabaseHas('processes', [
        'name' => $process->name,
    ]);
});

test('National Coordinator can delete a process', function () {

    $user = User::factory()->withRole(UserRole::NationalCoordinator)->create();
    $this->actingAs($user);
    $process = Process::factory()->create();

    $response = $this->delete("/api/processes/{$process->id}");

    $response->assertStatus(Response::HTTP_NO_CONTENT);
    $this->assertSoftDeleted($process);
});

test('Other users cannot delete a process', function () {

    $user = User::factory()->create();
    $this->actingAs($user);
    $process = Process::factory()->create();

    $response = $this->delete("/api/processes/{$process->id}");

    $response->assertStatus(Response::HTTP_FORBIDDEN);
    $this->assertNotSoftDeleted($process);
});

test('National Coordinator can restore a process', function () {

    $user = User::factory()->withRole(UserRole::NationalCoordinator)->create();
    $this->actingAs($user);
    $process = Process::factory()->create();
    $process->delete();

    $response = $this->patch("/api/processes/{$process->id}");

    $response->assertStatus(Response::HTTP_NO_CONTENT);
    $this->assertNotSoftDeleted($process);
});

test('Other users cannot restore a process', function () {

    $user = User::factory()->create();
    $this->actingAs($user);
    $process = Process::factory()->create();
    $process->delete();

    $response = $this->patch("/api/processes/{$process->id}");

    $response->assertStatus(Response::HTTP_FORBIDDEN);
    $this->assertSoftDeleted($process);
});