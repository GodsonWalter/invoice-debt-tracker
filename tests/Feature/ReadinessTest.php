<?php

test('readiness endpoint returns a minimal ready response', function (): void {
    $this->getJson('/ready')
        ->assertOk()
        ->assertExactJson(['status' => 'ready'])
        ->assertJsonMissingPath('database')
        ->assertJsonMissingPath('environment');
});
