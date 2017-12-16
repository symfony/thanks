<p align="center"><a href="https://symfony.com" target="_blank">
    <img src="https://symfony.com/logos/symfony_black_02.svg">
</a></p>

Give thanks (in the form of [github ⭐](https://help.github.com/articles/about-stars/)) to your fellow PHP package maintainers (not limited to Symfony components)!

Install
-------

Install this as any other (dev) Composer package:
```sh
$ composer require --dev symfony/thanks
```

Usage
-----

```sh
$ composer thanks
```

This will find all of your Composer dependencies, find their github.com repository, and star their github repositories. This was inspired by `cargo thanks`, which was inspired in part by Medium's clapping button as a way to show thanks for someone elses work you've found enjoyment in.

If you're wondering why did some dependencies get thanked and not others, the answer is that this plugin only supports github.com at the moment. Pull requests are welcome to add support for thanking packages hosted on other services.

Original idea by Doug Tangren (softprops) 2017 for Rust (thanks!)

Implemented by Nicolas Grekas (SensioLabs & Blackfire.io) 2017 for PHP.
