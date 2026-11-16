<?php

namespace App\Support;

use App\Models\Module;
use App\Models\ModuleDetail;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Menu sidebar dibangun dari master_module + master_module_detail,
 * hanya menampilkan menu yang punya halaman (route_name terdaftar) dan boleh dilihat user.
 */
class Menu
{
    /** @return Collection<int, array{module: Module, items: Collection}> */
    public static function for(User $user): Collection
    {
        return Module::with(['details' => fn ($q) => $q->where('is_active', true)])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Module $module) => [
                'module' => $module,
                'items' => $module->details->filter(fn ($d) => $d->route_name
                    && Route::has($d->route_name)
                    && $user->hasAccess($d->code, 'view'))->values(),
            ])
            ->filter(fn ($m) => $m['items']->isNotEmpty())
            ->values();
    }

    /**
     * Module yang sedang dibuka, berdasarkan route saat ini.
     * 'master.roles.access' cocok dengan menu 'master.roles.index' (prefix 'master.roles').
     */
    public static function active(Collection $menu): ?array
    {
        return $menu->first(fn ($group) => $group['items']->contains(fn ($item) => self::isActive($item->route_name)));
    }

    /** Menu (module detail) yang sedang dibuka, mis. untuk memilih panduan modul. */
    public static function activeItem(Collection $menu): ?ModuleDetail
    {
        foreach ($menu as $group) {
            $item = $group['items']->first(fn ($i) => self::isActive($i->route_name));
            if ($item) {
                return $item;
            }
        }

        return null;
    }

    public static function isActive(string $routeName): bool
    {
        return request()->routeIs(Str::beforeLast($routeName, '.').'.*');
    }

    /** Warna ikon aplikasi di halaman beranda (gradasi keluarga biru). */
    public static function color(string $moduleCode): string
    {
        return [
            'MASTER' => 'linear-gradient(145deg, #7d8cff, #4c5fe8)',
            'INBOUND' => 'linear-gradient(145deg, #4fc3ff, #007aff)',
            'OUTBOUND' => 'linear-gradient(145deg, #5ad7e8, #0a84c6)',
            'INVENTORY' => 'linear-gradient(145deg, #3d8bff, #1e40af)',
        ][$moduleCode] ?? 'linear-gradient(145deg, #94a3b8, #475569)';
    }
}
