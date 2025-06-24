<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

$routes->options('(:any)', fn() => response(200));
$session = session();
$routes->get('/', 'Home::index');

$routes->post('login', 'User::login');
$routes->get('logout', 'User::logout');
$routes->get('getSchoolList', 'User::getSchoolList');

$routes->get('point/getNewRecord', 'Point::getNewRecord');
$routes->get('point/getLastWeekRecord', 'Point::getLastWeekRecord');
$routes->post('point/getBonusBySchoolByWeek', 'Point::getBonusBySchoolByWeek', ['filter' => 'auth:uid']);
$routes->post('point/getBonusByUserByWeek', 'Point::getBonusByUserByWeek', ['filter' => 'auth:uid']);
$routes->post('point/getBonusInfo', 'Point::getBonusInfo', ['filter' => 'auth:uid']);

$routes->get('task/getBonusBySchoolLastWeek', 'Task::getBonusBySchoolLastWeek');
$routes->get('task/getBonusBySchoolNow', 'Task::getBonusBySchoolNow');
$routes->get('task/getExtraBonusByUser', 'Task::getExtraBonusByUser');
$routes->get('task/getExtraBonusBySchool', 'Task::getExtraBonusBySchool');
