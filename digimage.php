<?php

/*

Notes:
- If you're working with large image files, you'll want to make sure you
  allocate adequate memory via memory_limit, either to your php.ini file or via
  the following directive at the top of your script:
  ini_set('memory_limit', '512M');
- For some of the built-in PHP image operations, I am re-disabling the alpha
  blending on the image. This is because certain functions, like imagerotate(),
  do not modify a GdImage reference, but rather return an entirely new instance.
  By default, images have alpha blending enabled, so it must be turned off if
  we're getting a brand-new image. We want alpha blending off as most (if not
  all) of the operations involved here have the intention of replacing pixels
  rather than mixing them.

Citations:
- Color-removal solution by Mark Random on Stack Overflow:
    https://stackoverflow.com/a/55233732
- Hue-shifting solution by Tatu Ulmanen on Stack Overflow:
    https://stackoverflow.com/a/1890450
- Saturation solution by Radu Motisan on PocketMagic:
    https://www.pocketmagic.net/enhance-saturation-in-images-programatically

*/

/**
 * DigiMage is a class for manipulating images.
 *
 * @author  Digipotion
 * @version 1.0
 * @see     https://github.com/Digipotion/DigiMage
 */
class DigiMage
{
  private GdImage $img;
  private bool $loggingEnabled;
  
  /**
   * Create a new image manipulator.
   *
   * @param bool $loggingEnabled Toggles status logging to console via echo.
   */
  function __construct(bool $loggingEnabled = false)
  {
    $this->loggingEnabled = $loggingEnabled;
  }
  
  /**
   * Outputs a status message to the console when logging is enabled.
   *
   * @param string $msg The status message.
   */
  private function logStatus(string $msg)
  {
    if ($this->loggingEnabled)
    {
      echo $msg;
    }
  }
  
// - File Handling Operations ----------------------------------------------- //
  
  /**
   * Loads an image from a PNG file.
   *
   * @param string $filename The input filename with extension.
   */
  public function loadPng(string $filename)
  {
    $this->img = imagecreatefrompng($filename);
    if(!imageistruecolor($this->img))
    {
      imagepalettetotruecolor($this->img);
    }
    imagealphablending($this->img, false);
  }
  
  /**
   * Loads an image from a JPEG file.
   *
   * @param string $filename The input filename with extension.
   */
  public function loadJpg(string $filename)
  {
    $this->img = imagecreatefromjpeg($filename);
    if(!imageistruecolor($this->img))
    {
      imagepalettetotruecolor($this->img);
    }
    imagealphablending($this->img, false);
  }
  
  /**
   * Saves the image to a PNG file.
   *
   * @param string $filename The output filename with extension.
   */
  public function savePng(string $filename)
  {
    $this->logStatus("Saving image (PNG) to: $filename" . PHP_EOL);
    imagesavealpha($this->img, true);
    imagepng($this->img, $filename);
  }
  
  /**
   * Saves the image to a JPEG file.
   *
   * @param string $filename The output filename with extension.
   */
  public function saveJpg(string $filename, int $quality = 85)
  {
    $this->logStatus("Saving image (JPG) to: $filename" . PHP_EOL);
    
    if ($quality > 100 || $quality < 0)
    {
      throw new Exception('Invalid parameter supplied for JPEG quality level. '
        . 'Must be between 0 and 100 inclusive.');
    }
    
    $canvas = imagecreatetruecolor(imagesx($this->img), imagesy($this->img));
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
    imagealphablending($canvas, true);
    imagecopy($canvas, $this->img, 0, 0, 0, 0,
      imagesx($this->img), imagesy($this->img));
    imagejpeg($canvas, $filename, $quality);
  }
  
// - Helper/Utility Functions ----------------------------------------------- //
  
  /**
   * Iterates through each pixel in the image, executing a callback for each.
   *
   * @param callable $callback The callback to be performed on each pixel.
   *                           Accepts 2 arguments (int $x, int $y),
   *                           representing the current pixel location.
   */
  private function walkPixels(callable $callback)
  {
    $lastBar = 10;
    $this->logStatus('[');
    for($x=0; $x < imagesx($this->img); $x++)
    {
      // Update progress bar.
      $progress = ($x / imagesx($this->img)) * 100;
      $progress = floor($progress / 10) * 10;
      $progress += 10;
      if ($progress !== $lastBar)
      {
        $this->logStatus('*');
        $lastBar = $progress;
      }
      
      for($y=0; $y < imagesy($this->img); $y++)
      {
        // Execute callback.
        $callback($x, $y);
      }
    }
    $this->logStatus('] done.' . PHP_EOL);
  }
  
  /**
   * Returns the height of the image.
   *
   * @return int The height of the image in pixels.
   */
  public function getHeight(): int
  {
    return imagesy($this->img);
  }
  
  /**
   * Converts a color to floating-point (0.0 ~ 1.0) HSLA values.
   *
   * @param int $color An integer-based color, i.e. one from imagecolorat().
   * @return array A numeric array containing the hue, saturation, luminosity,
   *               and alpha values of the color. Scales are 0.0 ~ 1.0 for all
   *               values.
   */
  public function getHsla(int $color): array
  {
    list($r, $g, $b, $a) = $this->getRgba($color);
    list($h, $s, $l) = $this->rgbToHsl($r, $g, $b);
    
    return array($h, $s, $l, $a);
  }
  
  /**
   * Returns the active image object.
   *
   * @return GdImage The active image object.
   */
  public function getImage(): GdImage
  {
    return $this->img;
  }
  
  /**
   * Converts a color to 8-bit (0 ~ 255) integer RGBA values.
   *
   * @param int $color An integer-based color, i.e. one from imagecolorat().
   * @return array A numeric array containing the red, green, blue, and alpha
   *               values of the color. Scales are ?????
   */
  public function getRgba(int $color): array
  {
    $r = ($color >> 16) & 0xFF;
    $g = ($color >> 8) & 0xFF;
    $b = $color & 0xFF;
    $a = ($color & 0x7F000000) >> 24;
    
    return array($r, $g, $b, $a);
  }
  
  /**
   * Converts floating-point HSL values to 8-bit integer RGB values.
   *
   * @param $h float The color's hue value. 0.0 ~ 1.0.
   * @param $s float The color's saturation value. 0.0 ~ 1.0.
   * @param $l float The color's luminosity value. 0.0 ~ 1.0.
   * @return array A numeric array containing the red, green, and blue values of
   *               the color.
   */
  public function hslToRgb(float $h, float $s, float $v): array
  {
    if ($h > 1 || $h < 0)
    {
      throw new Exception('Invalid parameter supplied for hue value. '
        . 'Must be between 0.0 and 1.0 inclusive.');
    }
    
    if ($s > 1 || $s < 0)
    {
      throw new Exception('Invalid parameter supplied for saturation value. '
        . 'Must be between 0.0 and 1.0 inclusive.');
    }
    
    if ($v > 1 || $v < 0)
    {
      throw new Exception('Invalid parameter supplied for luminosity value. '
        . 'Must be between 0.0 and 1.0 inclusive.');
    }
    
    if($s == 0)
    {
      $r = $g = $B = $v * 255;
    }
    else
    {
      $var_H = $h * 6;
      $var_i = floor( $var_H );
      $var_1 = $v * ( 1 - $s );
      $var_2 = $v * ( 1 - $s * ( $var_H - $var_i ) );
      $var_3 = $v * ( 1 - $s * (1 - ( $var_H - $var_i ) ) );

      if      ($var_i == 0) {$var_R = $v    ; $var_G = $var_3; $var_B = $var_1;}
      else if ($var_i == 1) {$var_R = $var_2; $var_G = $v    ; $var_B = $var_1;}
      else if ($var_i == 2) {$var_R = $var_1; $var_G = $v    ; $var_B = $var_3;}
      else if ($var_i == 3) {$var_R = $var_1; $var_G = $var_2; $var_B = $v    ;}
      else if ($var_i == 4) {$var_R = $var_3; $var_G = $var_1; $var_B = $v    ;}
      else                  {$var_R = $v    ; $var_G = $var_1; $var_B = $var_2;}

      $r = $var_R * 255;
      $g = $var_G * 255;
      $B = $var_B * 255;
    }
    
    return array((int)$r, (int)$g, (int)$B);
  }
  
  /**
   * Converts 8-bit integer RGB values to floating-point HSL values.
   *
   * @param $r int The color's red value. 0 ~ 255.
   * @param $g int The color's green value. 0 ~ 255.
   * @param $b int The color's blue value. 0 ~ 255.
   * @return array A numeric array containing the hue, saturation, and
                   luminosity values of the color.
   */
  public function rgbToHsl(int $r, int $g, int $b): array
  {
    if ($r > 255 || $r < 0)
    {
      throw new Exception('Invalid parameter supplied for red value. '
        . 'Must be between 0 and 255 inclusive.');
    }
    
    if ($g > 255 || $g < 0)
    {
      throw new Exception('Invalid parameter supplied for green value. '
        . 'Must be between 0 and 255 inclusive.');
    }
    
    if ($b > 255 || $b < 0)
    {
      throw new Exception('Invalid parameter supplied for blue value. '
        . 'Must be between 0 and 255 inclusive.');
    }
    
    $var_R = ($r / 255);
    $var_G = ($g / 255);
    $var_B = ($b / 255);

    $var_Min = min($var_R, $var_G, $var_B);
    $var_Max = max($var_R, $var_G, $var_B);
    $del_Max = $var_Max - $var_Min;

    $v = $var_Max;

    if ($del_Max == 0)
    {
      $h = 0;
      $s = 0;
    }
    else
    {
      $s = $del_Max / $var_Max;

      $del_R = ( ( ( $var_Max - $var_R ) / 6 ) + ( $del_Max / 2 ) ) / $del_Max;
      $del_G = ( ( ( $var_Max - $var_G ) / 6 ) + ( $del_Max / 2 ) ) / $del_Max;
      $del_B = ( ( ( $var_Max - $var_B ) / 6 ) + ( $del_Max / 2 ) ) / $del_Max;

      if      ($var_R == $var_Max) $h = $del_B - $del_G;
      else if ($var_G == $var_Max) $h = ( 1 / 3 ) + $del_R - $del_B;
      else if ($var_B == $var_Max) $h = ( 2 / 3 ) + $del_G - $del_R;

      if ($h < 0) $h++;
      if ($h > 1) $h--;
    }

    return array($h, $s, $v);
  }
  
  /**
   * Returns the width of the image.
   *
   * @return int The width of the image in pixels.
   */
  public function getWidth(): int
  {
    return imagesx($this->img);
  }
  
// - Image Operations ------------------------------------------------------- //
  
  /**
   * Alters the brightness of the image.
   *
   * @param int $brightness How much to brighten/darken the image. -100 ~ 100.
                            Default: 0.
   */
  public function brightness(int $brightness)
  {
    $this->logStatus('Altering brightness... ');
    
    if ($brightness > 100 || $brightness < -100)
    {
      throw new Exception('Invalid parameter supplied for brightness value. '
        . 'Must be between -100 and 100 inclusive.');
    }
    
    if ($brightness === 0)
    {
      $this->logStatus('skipping.' . PHP_EOL);
    }
    else
    {
      imagefilter($this->img, IMG_FILTER_BRIGHTNESS,
        (int)(255 * $brightness / 100));
      $this->logStatus('done.' . PHP_EOL);
    }
  }
  
  /**
   * Adds transparency to the image based on each pixel's brightness level.
   *
   * If the image contains color, it will be converted to grayscale using a
   * simple averaging function. I do not see a feasible way to make a color
   * image become partially semi-transparent without losing color information.
   */
  public function brightnessToAlpha()
  {
    $this->logStatus('Converting pixel brightness to alpha... ');
    $this->walkPixels(function($x, $y)
    {
      $color = imagecolorat($this->img, $x, $y);
      list($r, $g, $b, $a) = $this->getRgba($color);
      // Ignore pixels that are already translucent/transparent.
      if ($a === 0)
      {
        $gray = ($r + $g + $b) / 765 * 255;
        $a = (int)(($gray / 255.0) * 127);
      }
      
      $newColor = imagecolorallocatealpha($this->img, 0, 0, 0, $a);
      imagesetpixel($this->img, $x, $y, $newColor);
    });
  }
  
  /**
   * Colorizes the image.
   *
   * @param $r int The amount of influencing red. 0 ~ 255.
   * @param $g int The amount of influencing green. 0 ~ 255.
   * @param $b int The amount of influencing blue. 0 ~ 255.
   */
  public function colorize(int $r, int $g, int $b)
  {
    $this->logStatus('Colorizing... ');
    
    if ($r > 255 || $r < 0)
    {
      throw new Exception('Invalid parameter supplied for red value. '
        . 'Must be between 0 and 255 inclusive.');
    }
    
    if ($g > 255 || $g < 0)
    {
      throw new Exception('Invalid parameter supplied for green value. '
        . 'Must be between 0 and 255 inclusive.');
    }
    
    if ($b > 255 || $b < 0)
    {
      throw new Exception('Invalid parameter supplied for blue value. '
        . 'Must be between 0 and 255 inclusive.');
    }
    
    if ($r === 0 && $g === 0 && $b === 0)
    {
      $this->logStatus('skipping.' . PHP_EOL);
    }
    else
    {
      imagefilter($this->img, IMG_FILTER_COLORIZE, $r, $g, $b);
      $this->logStatus('done.' . PHP_EOL);
    }
  }
  
  /**
   * Alters the contrast of the image.
   *
   * @param int $contrast How much to adjust the contrast. -100 ~ 100.
                          Default: 0.
   */
  public function contrast(int $contrast)
  {
    $this->logStatus('Altering contrast... ');
    
    if ($contrast > 100 || $contrast < -100)
    {
      throw new Exception('Invalid parameter supplied for contrast value. '
        . 'Must be between -100 and 100 inclusive.');
    }
    
    if ($contrast === 0)
    {
      $this->logStatus('skipping.' . PHP_EOL);
    }
    else
    {
      imagefilter($this->img, IMG_FILTER_CONTRAST, 0 - $contrast);
      $this->logStatus('done.' . PHP_EOL);
    }
  }
  
  /**
   * Removes color from the image.
   *
   * This is essentially a grayscale adjustment with the ability to adjust how
   * colors get filtered. This can be used to remove general colors from an
   * image when converting it to grayscale. The implementation is similar to
   * Photoshop's "Black and White" adjustment layer and uses the same default
   * values.
   
   TODO: Is it really -200 ~ 300? Or is it -100 ~ 100?
   
   *
   * @param int $r_w The weight of influencing red. -200 ~ 300. Default: 40.
   * @param int $y_w The weight of influencing yellow. -200 ~ 300. Default: 60.
   * @param int $g_w The weight of influencing green. -200 ~ 300. Default: 40.
   * @param int $c_w The weight of influencing cyan. -200 ~ 300. Default: 60.
   * @param int $b_w The weight of influencing blue. -200 ~ 300. Default: 20.
   * @param int $m_w The weight of influencing magenta. -200 ~ 300. Default: 80.
   */
  public function decolorize(
    int $r_w = 40, int $y_w = 60, int $g_w = 40,
    int $c_w = 60, int $b_w = 20, int $m_w = 80)
  {
    $this->logStatus('Converting image to grayscale... ');
    
    if ($r_w > 300 || $r_w < -200)
    {
      throw new Exception('Invalid parameter supplied for red weight. '
        . 'Must be between -200 and 300 inclusive.');
    }
    
    if ($y_w > 300 || $y_w < -200)
    {
      throw new Exception('Invalid parameter supplied for yellow weight. '
        . 'Must be between -200 and 300 inclusive.');
    }
    
    if ($g_w > 300 || $g_w < -200)
    {
      throw new Exception('Invalid parameter supplied for green weight. '
        . 'Must be between -200 and 300 inclusive.');
    }
    
    if ($c_w > 300 || $c_w < -200)
    {
      throw new Exception('Invalid parameter supplied for cyan weight. '
        . 'Must be between -200 and 300 inclusive.');
    }
    
    if ($b_w > 300 || $b_w < -200)
    {
      throw new Exception('Invalid parameter supplied for blue weight. '
        . 'Must be between -200 and 300 inclusive.');
    }
    
    if ($m_w > 300 || $m_w < -200)
    {
      throw new Exception('Invalid parameter supplied for magenta weight. '
        . 'Must be between -200 and 300 inclusive.');
    }
    
    $this->walkPixels(function($x, $y) use ($r_w, $y_w, $g_w, $c_w, $b_w, $m_w)
    {
      $color = imagecolorat($this->img, $x, $y);
      
      $r_w /= 100;
      $y_w /= 100;
      $g_w /= 100;
      $c_w /= 100;
      $b_w /= 100;
      $m_w /= 100;
      
      list($r, $g, $b, $a) = $this->getRgba($color);
      
      $gray = min($r, $g, $b);
      $r -= $gray;
      $g -= $gray;
      $b -= $gray;
      
      if ($r === 0)
      {
          $cyan = min($g, $b);
          $g -= $cyan;
          $b -= $cyan;
          $gray += $cyan * $c_w + $g * $g_w + $b * $b_w;
      }
      else if ($g === 0)
      {
          $magenta = min($r, $b);
          $r -= $magenta;
          $b -= $magenta;
          $gray += $magenta * $m_w + $r * $r_w + $b * $b_w;
      }
      else
      {
          $yellow = min($r, $g);
          $r -= $yellow;
          $g -= $yellow;
          $gray += $yellow * $y_w + $r * $r_w + $g * $g_w;
      }
      
      $gray = max(0, min(255, (int)round($gray)));
      
      $newColor = imagecolorallocatealpha($this->img, $gray, $gray, $gray, $a);
      imagesetpixel($this->img, $x, $y, $newColor);
    });
  }
  
  /**
   * Flips the image horizontally.
   */
  public function flipHorizontal()
  {
    $this->logStatus('Flipping horizontally... ');
    imageflip($this->img, IMG_FLIP_HORIZONTAL);
    $this->logStatus('done.' . PHP_EOL);
  }
  
  /**
   * Flips the image vertically.
   */
  public function flipVertical()
  {
    $this->logStatus('Flipping vertically... ');
    imageflip($this->img, IMG_FLIP_VERTICAL);
    $this->logStatus('done.' . PHP_EOL);
  }
  
  /**
   * Adjusts the hue of the image.
   *
   * @param int $hue The 360-degree angle by which to shift the hue.
   */
  public function hue(int $hue)
  {
    $this->logStatus('Shifting hue... ');
    
    $hue = $hue % 360;
    if ($hue < 0)
    {
      $hue = 360 - abs($hue);
    }
    
    if ($hue === 0)
    {
      $this->logStatus('skipping.' . PHP_EOL);
    }
    else
    {
      $this->walkPixels(function($x, $y) use ($hue)
      {
        $color = imagecolorat($this->img, $x, $y);
        
        list($r, $g, $b, $a) = $this->getRgba($color);
        list($h, $s, $l) = $this->rgbToHsl($r, $g, $b);
        $h += $hue / 360;
        if ($h > 1)
        {
          $h--;
        }
        list($r, $g, $b) = $this->hslToRgb($h, $s, $l);
        
        $newColor = imagecolorallocatealpha($this->img, $r, $g, $b, $a);
        imagesetpixel($this->img, $x, $y, $newColor);
      });
    }
  }
  
  /**
   * Resizes the image's width and height.
   *
   * @param int $width The new width of the image in pixels.
   * @param int $height The new height of the image in pixels.
   * @param int $mode The resampling algorithm to use. Please see the official
                      PHP documentation for the imagescale function for a list
                      of available constants.
   */
  public function resize(
    int $width, int $height,
    int $mode = IMG_BICUBIC)
  {
    $this->logStatus('Resizing...');
    
    if ($width <= 0)
    {
      throw new Exception('Invalid parameter supplied for width. '
        . 'Must be greater than 0.');
    }
    
    if ($height <= 0)
    {
      throw new Exception('Invalid parameter supplied for height. '
        . 'Must be greater than 0.');
    }
    
    $this->img = imagescale($this->img, $width, $height, $mode);
    imagealphablending($this->img, false);
    $this->logStatus('done.' . PHP_EOL);
  }
  
  /**
   * Rotates the image counter clockwise.
   */
  public function rotateLeft()
  {
    $this->logStatus('Rotating left... ');
    $this->img = imagerotate($this->img, 90, 0);
    imagealphablending($this->img, false);
    $this->logStatus('done.' . PHP_EOL);
  }
  
  /**
   * Rotates the image clockwise.
   */
  public function rotateRight()
  {
    $this->logStatus('Rotating right... ');
    $this->img = imagerotate($this->img, -90, 0);
    imagealphablending($this->img, false);
    $this->logStatus('done.' . PHP_EOL);
  }
  
  /**
   * Adjusts the saturation of the image.
   *
   * @param int $saturation How much to saturate/desaturate. -100 ~ 100.
                            Default: 0.
   */
  public function saturation(int $saturation)
  {
    $this->logStatus('Adjusting image saturation... ');
    
    if ($saturation > 100 || $saturation < -100)
    {
      throw new Exception('Invalid parameter supplied for saturation value. '
        . 'Must be between -100 and 100 inclusive.');
    }
    
    // Float scale for saturation is 0 ~ 5
    if ($saturation >= 0)
    {
      $saturation /= 20;
    }
    // Float scale for desaturation is -1 ~ 0
    else
    {
      $saturation /= 100;
    }
    
    $this->walkPixels(function($x, $y) use ($saturation)
    {
      $color = imagecolorat($this->img, $x, $y);
      
      list($h, $s, $l, $a) = $this->getHsla($color);
      $s *= 255;
      
      if ($saturation >= 0)
      {
        $grayFactor = (float)$s / 255.0;
        $varInterval = 255 - $s;
        $s = $s + $saturation * $varInterval * $grayFactor;
      }
      else
      {
        $varInterval = $s;
        $s = $s + $saturation * $varInterval;
      }
      
      $s /= 255;
      $s = max(0, min($s, 1));
      list($r, $g, $b) = $this->hslToRgb($h, $s, $l);
      
      $newColor = imagecolorallocatealpha($this->img, $r, $g, $b, $a);
      imagesetpixel($this->img, $x, $y, $newColor);
    });
  }
  
  /**
   * Tiles the image to a specified width/height.
   *
   * @param int $width The target width of the tiled image.
   * @param int $height The target height of the tiled image.
   */
  public function tile(int $width, int $height)
  {
    $this->logStatus('Tiling image... ');
    
    $tileWidth = imagesx($this->img);
    $tileHeight = imagesy($this->img);
    
    if ($width < $tileWidth)
    {
      throw new Exception('Invalid parameter supplied for new image width. '
        . 'New image width must be greater than or equal to current width.');
    }
    
    if ($height < $tileHeight)
    {
      throw new Exception('Invalid parameter supplied for new image height. '
        . 'New image height must be greater than or equal to current height.');
    }
    
    if ($width === $tileWidth && $height === $tileHeight)
    {
      $this->logStatus('skipping.' . PHP_EOL);
    }
    else
    {
      $newImg = imagecreatetruecolor($width, $height);
      imagealphablending($newImg, false);
      $transparent = imagecolorallocatealpha($newImg, 0, 0, 0, 127);
      imagefill($newImg, 0, 0, $transparent);
      
      for ($x = 0; $x < $width; $x += $tileWidth)
      {
        for ($y = 0; $y < $height; $y += $tileHeight)
        {
          imagecopy($newImg, $this->img, $x, $y, 0, 0, $tileWidth, $tileHeight);
        }
      }
      $this->img = $newImg;
      $this->logStatus('done.' . PHP_EOL);
    }
  }
}

?>