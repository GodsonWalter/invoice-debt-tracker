<?php

test('unused scaffold API endpoints are not publicly exposed', function (): void {
    $this->getJson('/api/users')->assertNotFound();
    $this->postJson('/api/students', [
        'name' => 'Unexpected user',
        'email' => 'unexpected@example.test',
    ])->assertNotFound();
});
