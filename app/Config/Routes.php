<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

$routes->get('login', 'User::login');
$routes->get('logout', 'User::logout');
$routes->get('getSchoolList', 'User::getSchoolList');

$routes->get('point/getNewRecord', 'Point::getNewRecord');
$routes->get('point/getLastWeekRecord', 'Point::getLastWeekRecord');
$routes->get('point/getBonusBySchoolByWeek', 'Point::getBonusBySchoolByWeek');
$routes->get('point/getBonusByUserByWeek', 'Point::getBonusByUserByWeek');
$routes->get('point/getBonusInfo', 'Point::getBonusInfo', ['filter' => 'auth']);

$routes->get('task/getBonusBySchoolLastWeek', 'Task::getBonusBySchoolLastWeek');
$routes->get('task/getBonusBySchoolNow', 'Task::getBonusBySchoolNow');
$routes->get('task/getExtraBonusByUser', 'Task::getExtraBonusByUser');
$routes->get('task/getExtraBonusBySchool', 'Task::getExtraBonusBySchool');
