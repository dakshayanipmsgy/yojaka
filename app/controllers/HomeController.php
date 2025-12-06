<?php
class HomeController
{
    public function index()
    {
        $data = [
            'title' => 'Yojaka – Welcome',
        ];

        return yojaka_render_view('home', $data, 'main');
    }
}
