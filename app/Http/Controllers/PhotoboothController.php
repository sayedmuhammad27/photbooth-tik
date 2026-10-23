<?php

namespace App\Http\Controllers;

use App\Models\Template;

class PhotoboothController extends Controller
{
    /** Layar sambutan. */
    public function index()
    {
        return view('welcome');
    }

    /** Galeri template: hanya template aktif; filter & pencarian berjalan di browser. */
    public function setup()
    {
        $templates = Template::active()
            ->ordered()
            ->get()
            ->map(fn (Template $t) => $t->toBoothArray())
            ->values();

        return view('photobooth.setup', ['templates' => $templates]);
    }
}
