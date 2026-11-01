<?php

test('the home page is public', function () {
    $response = $this->get('/');

    $response->assertOk();
});
