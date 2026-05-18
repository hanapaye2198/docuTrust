<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\NotaryRequest;

// Check all users and their org IDs
$users = User::all(['id', 'name', 'role', 'organization_id']);
echo "=== USERS ===" . PHP_EOL;
foreach ($users as $u) {
    echo "  ID:{$u->id} name:{$u->name} role:{$u->role->value} org_id:{$u->organization_id}" . PHP_EOL;
}

echo PHP_EOL . "=== RECENT NOTARY REQUESTS ===" . PHP_EOL;
$requests = NotaryRequest::latest()->take(5)->get(['id', 'title', 'organization_id', 'requester_user_id', 'notary_user_id']);
foreach ($requests as $r) {
    echo "  ID:{$r->id} title:{$r->title} org_id:{$r->organization_id} requester:{$r->requester_user_id} notary:{$r->notary_user_id}" . PHP_EOL;
}
