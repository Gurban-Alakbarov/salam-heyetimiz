<?php
$content = file_get_contents(__DIR__ . '/v1.yaml');
preg_match_all('~\$ref:\s*[\x27\x22]#/components/(schemas|parameters|responses|headers|securitySchemes)/([A-Za-z0-9_]+)[\x27\x22]~', $content, $refs);

$used = [];
for ($i = 0; $i < count($refs[1]); $i++) {
    $used[] = [$refs[1][$i], $refs[2][$i]];
}
$used = array_values(array_unique(array_map(fn($p) => $p[0] . '/' . $p[1], $used)));

$buckets = ['schemas' => [], 'parameters' => [], 'responses' => [], 'headers' => [], 'securitySchemes' => []];
$lines = explode("\n", $content);
$current = null;
foreach ($lines as $line) {
    if (preg_match('/^  (schemas|parameters|responses|headers|securitySchemes):\s*$/', $line, $m)) {
        $current = $m[1];
        continue;
    }
    if (preg_match('/^  [a-zA-Z]+:\s*$/', $line) && !preg_match('/^  (schemas|parameters|responses|headers|securitySchemes):/', $line)) {
        $current = null;
    }
    if ($current && preg_match('/^    ([A-Za-z][A-Za-z0-9_]+):\s*$/', $line, $m)) {
        $buckets[$current][] = $m[1];
    }
}

echo 'Defined: schemas=' . count($buckets['schemas'])
    . ', parameters=' . count($buckets['parameters'])
    . ', responses=' . count($buckets['responses'])
    . ', headers=' . count($buckets['headers'])
    . ', securitySchemes=' . count($buckets['securitySchemes']) . "\n";
echo 'Unique refs used: ' . count($used) . "\n";

$missing = [];
foreach ($used as $entry) {
    [$bucket, $name] = explode('/', $entry);
    if (!in_array($name, $buckets[$bucket], true)) {
        $missing[] = $entry;
    }
}

if ($missing) {
    echo "MISSING:\n";
    foreach (array_unique($missing) as $m) echo "  $m\n";
    exit(1);
}
echo "All \$refs resolve.\n";

// Also check: every operationId is unique
preg_match_all('/operationId:\s*([A-Za-z][A-Za-z0-9_]+)/', $content, $opMatches);
$opIds = $opMatches[1];
$dupOps = array_keys(array_filter(array_count_values($opIds), fn($n) => $n > 1));
if ($dupOps) {
    echo "DUPLICATE operationIds:\n";
    foreach ($dupOps as $o) echo "  $o\n";
    exit(1);
}
echo 'operationIds: ' . count($opIds) . ' (all unique)' . "\n";

// Quick scan: every tag used appears in tags list
preg_match_all('/^\s*tags:\s*\[([^\]]+)\]/m', $content, $tagMatches);
$usedTags = [];
foreach ($tagMatches[1] as $list) {
    foreach (explode(',', $list) as $t) {
        $usedTags[trim($t)] = true;
    }
}
preg_match_all('/^\s*-\s*name:\s*(.+?)\s*$/m', $content, $defTagMatches);
$definedTags = array_map('trim', $defTagMatches[1]);
$missingTags = array_diff(array_keys($usedTags), $definedTags);
if ($missingTags) {
    echo "Tags used but not declared:\n";
    foreach ($missingTags as $t) echo "  $t\n";
    exit(1);
}
echo 'tags: ' . count($definedTags) . ' declared, ' . count($usedTags) . " used (all valid)\n";
