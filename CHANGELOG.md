# Changelog

All notable changes to this project will be documented in this file.

## 1.1.0 - 2018-09-17

- Cache models even if they have eagerloaded relations

- Add support for Laravel 5.4 and 5.7

## 1.1.4 - 2019-03-20

- Add support for Laravel 5.8

- Add ability to retrieve models that are using the SoftDeletes trait from the cache (or any kind of IS NULL/IS NOT NULL conditions)

## 1.1.5 - 2019-05-21

- Fix for models that have $appends attributes

## 1.1.7 - 2020-03-12

- Avoid the 'This cache store does not support tagging.' exception

## 1.1.9 - 2020-06-20

- Add support for Laravel 7

## 1.2.0 - 2020-10-06

- Add support for Laravel 8

- Remove support for Laravel 5
