<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    private const DOMAIN_FILE = 'branches/base-domain.txt';

    private function baseDomain(): string
    {
        return trim((string) Storage::disk('local')->get(self::DOMAIN_FILE));
    }

    public function index()
    {
        return view('admin.branches.index', ['branches' => Branch::orderBy('name')->paginate(15), 'baseDomain' => $this->baseDomain()]);
    }

    public function store(Request $request)
    {
        $branch = Branch::create($this->branchData($request));

        return response()->json(['success' => true, 'message' => 'Branch published.', 'url' => route('branches.show', $branch->slug, false)], 201);
    }

    public function update(Request $request, Branch $branch)
    {
        $branch->update($this->branchData($request, $branch));

        return response()->json(['success' => true, 'message' => 'Branch updated.', 'url' => route('branches.show', $branch->slug, false)]);
    }

    public function destroy(Request $request, Branch $branch)
    {
        abort_if($request->user()->isAssistantAdmin(), 403);
        $branch->delete();

        return response()->json(['success' => true, 'message' => 'Branch deleted.']);
    }

    private function branchData(Request $request, ?Branch $branch = null): array
    {
        $request->merge(['slug' => strtolower(trim((string) $request->input('slug')))]);
        $uniqueSlug = Rule::unique('branches', 'slug');
        if ($branch) $uniqueSlug->ignore($branch);
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'slug' => ['required', 'string', 'max:63', 'regex:/\A[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\z/', $uniqueSlug, 'not_in:www,admin,api,mail,ftp,cdn,assets,storage,autodiscover'],
            'description' => 'required|string|max:3000',
            'hero' => ($branch ? 'nullable' : 'required').'|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);
        unset($data['hero']);
        if ($request->hasFile('hero')) {
            $path = $request->file('hero')->store('branch-heroes', 'public');
            abort_unless($path, 500, 'The branch image could not be saved.');
            $data['hero_path'] = $path;
        }
        return $data;
    }

    public function updateDomain(Request $request)
    {
        $request->merge(['base_domain' => strtolower(trim((string) $request->input('base_domain')))]);
        $data = $request->validate(['base_domain' => ['nullable', 'string', 'max:189', 'regex:/\A(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}\z/']]);
        abort_unless(Storage::disk('local')->put(self::DOMAIN_FILE, $data['base_domain'] ?? ''), 500);
        return response()->json(['success' => true, 'message' => 'Branch base domain saved.']);
    }

    public function home(Request $request)
    {
        $base = $this->baseDomain();
        $host = $request->getHost();
        if ($base !== '' && str_ends_with($host, '.'.$base)) {
            $slug = substr($host, 0, -strlen('.'.$base));
            if ($slug !== 'www') {
                abort_if(str_contains($slug, '.'), 404);
                return $this->landing($slug);
            }
        }
        return file_get_contents(public_path('index.html'));
    }

    public function show(string $slug)
    {
        return $this->landing($slug);
    }

    public function hero(string $slug)
    {
        $path = Branch::where('slug', $slug)->firstOrFail()->hero_path;
        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, ['X-Content-Type-Options' => 'nosniff']);
    }

    private function landing(string $slug)
    {
        $branch = Branch::where('slug', $slug)->firstOrFail();
        $shell = file_get_contents(public_path('index.html'));
        $startMarker = '<!-- campus-content:start -->';
        $endMarker = '<!-- campus-content:end -->';
        $start = strpos($shell, $startMarker);
        $end = strpos($shell, $endMarker);
        abort_if($start === false || $end === false || $end <= $start, 500, 'The branch landing template is unavailable.');

        $hero = view('branches.hero', compact('branch'))->render();
        $html = substr_replace($shell, $hero, $start, $end + strlen($endMarker) - $start);
        $html = str_replace('<head>', '<head><base href="/">', $html);
        $html = str_replace('</head>', '<link rel="stylesheet" href="/branch-landing.css?v=6"></head>', $html);
        return response(str_replace('<title>GRA Test Prep — Powered by Artemis360</title>', '<title>'.e($branch->name).' | GRA Test Prep</title>', $html));
    }
}
