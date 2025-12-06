<?php
class AboutController
{
    public function index()
    {
        $data = [
            'title' => 'About Yojaka',
        ];

        return yojaka_render_view('about', $data, 'main');
    }
}
