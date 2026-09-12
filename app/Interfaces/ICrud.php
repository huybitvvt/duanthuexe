<?php

namespace App\Interfaces;

interface ICrud
{
    public function index(array $params);

    public function store(array $params);

    public function update($id, array $params);

    public function delete();

    public function all();
}
