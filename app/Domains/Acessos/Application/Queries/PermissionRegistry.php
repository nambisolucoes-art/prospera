<?php

namespace App\Domains\Acessos\Application\Queries;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class PermissionRegistry
{
    /** @return list<array{slug:string,scope:string,module:string,name:string}> */
    public function discover(): array
    {
        $slugs = [];
        foreach ([app_path(), base_path('routes')] as $directory) {
            foreach (File::allFiles($directory) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $source = File::get($file->getPathname());
                $slugPattern = '((?:account|platform|self)\\.[a-z][a-z0-9-]*\\.[a-z][a-z0-9-]*)';
                $patterns = [
                    '/[\'"]can:'.$slugPattern.'[\'"]/',
                    '/(?:authorize|can|cannot|allows|denies)\\(\\s*[\'"]'.$slugPattern.'[\'"]/',
                ];
                if ($file->getPathname() === app_path('Http/Middleware/SelfServicePermissions.php')) {
                    $patterns[] = '/[\'"]'.$slugPattern.'[\'"]/';
                }
                foreach ($patterns as $pattern) {
                    preg_match_all($pattern, $source, $matches);
                    foreach ($matches[1] as $slug) {
                        $slugs[$slug] = true;
                    }
                }
            }
        }
        ksort($slugs);
        $definitions = [];
        foreach (array_keys($slugs) as $slug) {
            [$scope,$module,$action] = explode('.', $slug);
            $definitions[] = ['slug' => $slug, 'scope' => $scope, 'module' => $module, 'name' => (config('permissions.actions.'.$action) ?? Str::headline($action)).': '.(config('permissions.modules.'.$module) ?? Str::headline($module))];
        }

        return $definitions;
    }
}
