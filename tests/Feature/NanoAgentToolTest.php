<?php

use App\Ai\Agents\NanoAgent;
use App\Ai\Tools\EditFile;
use App\Ai\Tools\GlobFiles;
use App\Ai\Tools\GrepFiles;
use App\Ai\Tools\ReadFile;
use App\Ai\Tools\RunBash;
use App\Ai\Tools\WriteFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->workspaceDirectory = 'storage/framework/testing/nano-tools-'.Str::uuid();

    File::ensureDirectoryExists(base_path($this->workspaceDirectory));
});

afterEach(function () {
    File::deleteDirectory(base_path($this->workspaceDirectory));
});

test('nano agent uses the existing local model and exposes coding tools', function () {
    $agent = new NanoAgent;

    expect($agent->instructions())
        ->toContain('concise coding assistant')
        ->toContain(base_path());

    expect($agent->tools())
        ->toHaveCount(6)
        ->each->toBeInstanceOf(Tool::class);
});

test('read file returns numbered lines from the requested range', function () {
    File::put(base_path($this->workspaceDirectory.'/notes.txt'), "alpha\nbeta\ngamma\n");

    $tool = new ReadFile;

    $result = $tool->handle(new Request([
        'path' => $this->workspaceDirectory.'/notes.txt',
        'start_line' => 2,
        'end_line' => 3,
    ]));

    expect((string) $result)
        ->toContain('Contents of')
        ->toContain('2: beta')
        ->toContain('3: gamma')
        ->not->toContain('1: alpha');
});

test('write file creates a new file inside the workspace', function () {
    $tool = new WriteFile;

    $result = $tool->handle(new Request([
        'path' => $this->workspaceDirectory.'/drafts/output.txt',
        'content' => 'hello from nano',
    ]));

    expect((string) $result)->toContain('Wrote');
    expect(File::get(base_path($this->workspaceDirectory.'/drafts/output.txt')))
        ->toBe('hello from nano');
});

test('edit file replaces the requested content', function () {
    File::put(base_path($this->workspaceDirectory.'/edit.txt'), 'before before');

    $tool = new EditFile;

    $result = $tool->handle(new Request([
        'path' => $this->workspaceDirectory.'/edit.txt',
        'search' => 'before',
        'replace' => 'after',
        'replace_all' => true,
    ]));

    expect((string) $result)->toContain('Replaced 2 matches');
    expect(File::get(base_path($this->workspaceDirectory.'/edit.txt')))
        ->toBe('after after');
});

test('edit file replaces only the first match when replace all is disabled', function () {
    File::put(base_path($this->workspaceDirectory.'/edit-first.txt'), 'before before');

    $tool = new EditFile;

    $result = $tool->handle(new Request([
        'path' => $this->workspaceDirectory.'/edit-first.txt',
        'search' => 'before',
        'replace' => 'after',
        'replace_all' => false,
    ]));

    expect((string) $result)->toContain('Replaced 1 match');
    expect(File::get(base_path($this->workspaceDirectory.'/edit-first.txt')))
        ->toBe('after before');
});

test('glob files finds matching files inside a directory', function () {
    File::ensureDirectoryExists(base_path($this->workspaceDirectory.'/src'));
    File::put(base_path($this->workspaceDirectory.'/src/One.php'), '<?php');
    File::put(base_path($this->workspaceDirectory.'/src/Two.txt'), 'text');

    $tool = new GlobFiles;

    $result = $tool->handle(new Request([
        'pattern' => 'src/*.php',
        'directory' => $this->workspaceDirectory,
    ]));

    expect((string) $result)
        ->toContain('One.php')
        ->not->toContain('Two.txt');
});

test('glob files works without a directory argument', function () {
    $directory = 'nano-tools-'.Str::uuid();
    File::ensureDirectoryExists(base_path($directory));
    File::put(base_path($directory.'/standalone.php'), '<?php');

    $tool = new GlobFiles;

    $result = $tool->handle(new Request([
        'pattern' => $directory.'/standalone.php',
    ]));

    expect((string) $result)->toContain($directory.'/standalone.php');

    File::deleteDirectory(base_path($directory));
});

test('grep files returns matching file paths and line numbers', function () {
    File::put(base_path($this->workspaceDirectory.'/search.php'), "<?php\nreturn 'needle';\n");
    File::put(base_path($this->workspaceDirectory.'/ignore.php'), "<?php\nreturn 'other';\n");

    $tool = new GrepFiles;

    $result = $tool->handle(new Request([
        'query' => 'needle',
        'glob' => '*.php',
        'directory' => $this->workspaceDirectory,
    ]));

    expect((string) $result)
        ->toContain('search.php:2')
        ->not->toContain('ignore.php');
});

test('grep files works without a directory argument', function () {
    $directory = 'nano-tools-'.Str::uuid();
    File::ensureDirectoryExists(base_path($directory));
    File::put(base_path($directory.'/search-global.php'), "<?php\nreturn 'needle';\n");

    $tool = new GrepFiles;

    $result = $tool->handle(new Request([
        'query' => 'needle',
        'glob' => $directory.'/search-global.php',
    ]));

    expect((string) $result)->toContain($directory.'/search-global.php:2');

    File::deleteDirectory(base_path($directory));
});

test('run bash executes commands from the project workspace', function () {
    $tool = new RunBash;

    $result = $tool->handle(new Request([
        'command' => 'pwd',
        'timeout' => 5,
    ]));

    expect((string) $result)
        ->toContain('Exit code: 0')
        ->toContain(base_path());
});
