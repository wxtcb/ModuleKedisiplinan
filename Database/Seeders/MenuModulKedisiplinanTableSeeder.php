<?php

namespace Modules\Kedisiplinan\Database\Seeders;

use App\Models\Core\Menu;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;

class MenuModulKedisiplinanTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();

        Menu::where('modul', 'Kedisiplinan')->delete();
        $menu = Menu::create([
            'modul' => 'Kedisiplinan',
            'label' => 'Data Kedisiplinan',
            'url' => 'kedisiplinan',
            'can' => serialize(['admin']),
            'icon' => 'fas fa-exclamation-circle',
            'urut' => 1,
            'parent_id' => 0,
            'active' => serialize(['kedisiplinan']),
        ]);
        if ($menu) {
            Menu::create([
                'modul' => 'Kedisiplinan',
                'label' => 'Alpha Pegawai',
                'url' => 'kedisiplinan/alpha',
                'can' => serialize(['admin']),
                'icon' => 'far fa-circle',
                'urut' => 1,
                'parent_id' => $menu->id,
                'active' => serialize(['kedisiplinan/alpha', 'kedisiplinan/alpha*']),
            ]);
        }
        if ($menu) {
            Menu::create([
                'modul' => 'Kedisiplinan',
                'label' => 'Disiplin Jam Kerja',
                'url' => 'kedisiplinan/disiplin',
                'can' => serialize(['admin']),
                'icon' => 'far fa-circle',
                'urut' => 1,
                'parent_id' => $menu->id,
                'active' => serialize(['kedisiplinan/disiplin', 'kedisiplinan/disiplin*']),
            ]);
        }
    }
}
