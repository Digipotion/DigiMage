# DigiMage

DigiMage is a class for transforming and filtering images in PHP.

## Usage

```php
$img = new Digimage();

$img->loadPng("cool_man.png");

$img->decolorize(116, 60, 40, 60, 20, 80);
$img->brightness(-24);
$img->contrast(90);
$img->brightnessToAlpha();

$img->savePng("altered_man.png");
```

## Requirements

- PHP >= 8.0

I believe the only thing potentially making PHP 8.0 a requirement is the absence of `imagedestroy()` calls. As of this version, [calls to this method are no longer required](https://php.watch/versions/8.0/gdimage). I am unsure of whether GdImages are freed from memory when moved out of scope and whether memory is freed when assigning one GdImage object to another. Despite searching, I could not find information regarding this. Therefore, this library may or may not work fine on earlier versions.

## License

DigiMage is licensed under the [MIT License](https://opensource.org/license/mit/).
