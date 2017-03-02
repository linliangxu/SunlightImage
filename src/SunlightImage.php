<?php
/**
 * SunlightImage Class.
 * @version 1.0.1
 * @package Plug
 * @author Linxu
 * @email lliangxu@qq.com
 * @website http://article.zhile.name
 * @date 2013-12-29
 */

class SunlightImage {

	private static $image;
	private static $info;
	private static $gif;
	public static $error;

	private static function open ($image) {
		 //检测图像文件
		if (!is_file($image)) {
			self::$error =  array('status' => 1, 'message' => '不存在的图像文件');
			return false;
		}

		 //获取图像信息
		$info = getimagesize($image);

		 //检测图像合法性
		if(false === $info || (IMAGETYPE_GIF === $info[2] && empty($info['bits']))){
			self::$error =  array('status' => 2, 'message' => '非法图像文件');
			return false;
		}

		 //设置图像信息
		 self::$info = array(
			 'width'  => $info[0],
			'height' => $info[1],
			'type'   => image_type_to_extension($info[2], false),
			'mime'   => $info['mime'],
		 );
		

		//打开图像
		if('gif' == self::$info['type']){
				self::$gif = new GIF($image);
				self::$image = imagecreatefromstring(self::$gif->image());
		} else {
				$fun = 'imagecreatefrom' . self::$info['type'];
				self::$image = $fun($image);
		}

		return true;
	}


	public static function format ($image, $rename) {
		if (!self::open($image)) {
			self::close();
			return false;
		}

		$info = pathinfo($rename);
		if (is_dir($info['dirname'])) {
			if (!is_writable($info['dirname'])) {
				self::$error = array('status' => 10, 'message' => '上传目录'.$info['dirname'].'不可写');
				return false;
			}
		} else {
			if (!mkdir($info['dirname'], 0777, true)) {
				self::$error = array('status' => 11, 'message' => '创建上传目录'.$info['dirname'].'失败');
				return false;
			}
		}
		$format = $info['extension'] == 'jpg' ? 'jpeg' : $info['extension'];

		if (self::$info['type'] == $format) {
			self::close();
			return rename($image, $rename);
		} else {
			self::$info['type'] = $format;
			
			unlink($image);
			
			$image = $info['dirname'] . '/' . $info['filename'] . '.' . $info['extension'];
			self::save($image);

			return true;
		}

	}


	/**
	 * 裁剪图像
	 * @param  [type]  $image  [description]
	 * @param  integer $w      [description]
	 * @param  integer $h      [description]
	 * @param  integer $x      [description]
	 * @param  integer $y      [description]
	 * @param  [type]  $width  [description]
	 * @param  [type]  $height [description]
	 * @return [type]          [description]
	 */
	public static function crop ($image, $w = 240, $h = 240, $x = 0, $y = 0, $width = null, $height = null) {
		if (!self::open($image)) {
			self::close();
			return false;
		}

		//设置保存尺寸
		empty($width) && $width = $w;
		empty($height) && $height = $h;

		do {
			//创建新图像
			$sourceImage = imagecreatetruecolor($width, $height);
			// 调整默认颜色
			$color = imagecolorallocate($sourceImage, 255, 255, 255);
			imagefill($sourceImage, 0, 0, $color);

			//裁剪
			imagecopyresampled($sourceImage, self::$image, 0, 0, $x, $y, $width, $height, $w, $h);
			imagedestroy(self::$image); //销毁原图

			//设置新图像
			self::$image = $sourceImage;
		} while (!empty(self::$gif) && self::nextGif());

		self::$info['width'] = $width;
		self::$info['height'] = $height;

		self::save($image);
		
		return self::$info;

	}


	/**
	 * 生成缩略图
	 * @param  [type]  $image     [description]
	 * @param  integer $width     [description]
	 * @param  integer $height    [description]
	 * @param  [type]  $directory [description]
	 * @param  integer $type      [description]
	 * @return [type]             [description]
	 */
	public static function thumb ($image, $directory = null, $type = 1, $width = 320, $height = 320) {
		if (!self::open($image)) {
			self::close();
			return false;
		}

		$name = basename($image);

		if (!$directory) {
			$image = dirname($image);
			$image .= '/thumb/';
		} else {
			$image = $directory . '/';
		}
		
		if (is_dir($image)) {
			if (!is_writable($image)) {
				self::close();
				self::$error = array('status' => 10, 'message' => $image.'目录不可写');
				return false;
			}
		} else {
			if (!mkdir($image, 0777, true)) {
				self::close();
				self::$error = array('status' => 11, 'message' => '创建'.$image.'目录失败');
				return false;
			}
		}

		$image .= $name;

		 //原图宽度和高度
		$w = self::$info['width'];
		$h = self::$info['height'];

		/* 计算缩略图生成的必要参数 */
		switch ($type) {
			/* 等比例缩放 */
			case 1:
				//原图尺寸小于缩略图尺寸则不进行缩略
				if($w <= $width && $h <= $height) {
					self::$info['status'] = 0;
					self::save($image);
					return self::$info;
				}

				//计算缩放比例
				$scale = min($width/$w, $height/$h);
				
				//设置缩略图的坐标及宽度和高度
				$x = $y = 0;
				$width  = $w * $scale;
				$height = $h * $scale;
				break;

			/*等比例缩放（按宽高最大比率）*/
			case 2:
				if ($w <= $width || $h <= $height) {
					self::$info['status'] = 0;
					self::save($image);
					return self::$info;
				}

				//计算缩放比例
				$scale = max($width/$w, $height/$h);
				$x = $y = 0;
				$width  = $w * $scale;
				$height = $h * $scale;
				break;

			/*等比例缩放（按宽比率）*/
			case 3:
				if ($w <= $width) {
					self::$info['status'] = 0;
					self::save($image);
					return self::$info;
				}

				//计算缩放比例
				$scale = $width/$w;
				$x = $y = 0;
				$width  = $w * $scale;
				$height = $h * $scale;
				break;

			/*等比例缩放（按高比率）*/
			case 4:
				if ($h <= $height) {
					self::$info['status'] = 0;
					self::save($image);
					return self::$info;
				}

				//计算缩放比例
				$scale = $height/$h;
				$x = $y = 0;
				$width  = $w * $scale;
				$height = $h * $scale;
				break;

			/* 居中裁剪 */
			case 5:
				//计算缩放比例
				$scale = max($width/$w, $height/$h);

				//设置缩略图的坐标及宽度和高度
				$w = $width/$scale;
				$h = $height/$scale;
				$x = (self::$info['width'] - $w)/2;
				$y = (self::$info['height'] - $h)/2;
				break;

			/* 左上角裁剪 */
			case 7:
				//计算缩放比例
				$scale = max($width/$w, $height/$h);

				//设置缩略图的坐标及宽度和高度
				$x = $y = 0;
				$w = $width/$scale;
				$h = $height/$scale;
				break;

			/* 右下角裁剪 */
			default:
				//计算缩放比例
				$scale = max($width/$w, $height/$h);

				//设置缩略图的坐标及宽度和高度
				$w = $width/$scale;
				$h = $height/$scale;
				$x = self::$info['width'] - $w;
				$y = self::$info['height'] - $h;
				break;
		}

		/* 裁剪图像 */
		do {
			//创建新图像
			$sourceImage = imagecreatetruecolor($width, $height);
			// 调整默认颜色
			$color = imagecolorallocate($sourceImage, 255, 255, 255);
			imagefill($sourceImage, 0, 0, $color);

			//裁剪
			imagecopyresampled($sourceImage, self::$image, 0, 0, $x, $y, $width, $height, $w, $h);
			imagedestroy(self::$image); //销毁原图

			//设置新图像
			self::$image = $sourceImage;
		} while (!empty(self::$gif) && self::nextGif());

		self::$info['width'] = $width;
		self::$info['height'] = $height;

		self::save($image);
		
		return self::$info;

	}

	/**
	 * 添加水印
	 * @param  string  $source 水印图片路径
	 * @param  integer $locate 水印位置
	 * @param  integer $alpha  水印透明度
	 */
	public static function water ($image, $source, $locate = 9, $margin = 20) {
		if (!self::open($image)) {
			self::close();
			return false;
		}
		if (!is_file($source)) {
			self::close();
			self::$error = array('status' => 3, 'message' => '水印图像不存在');
			return false;
		}

		//获取水印图像信息
		$info = getimagesize($source);
		if(false === $info || (IMAGETYPE_GIF === $info[2] && empty($info['bits']))) {
			self::close();
			self::$error = array('status' => 4, 'message' => '非法水印文件');
			return false;
		}

		 //创建水印图像资源
		$fun   = 'imagecreatefrom' . image_type_to_extension($info[2], false);
		$water = $fun($source);

		//设定水印图像的混色模式
		imagealphablending($water, true);

		/* 设定水印位置 */
		switch ($locate) {
			/* 左上角水印 */
			case 1 :
				$x = $y = $margin;
				break;

			/* 上居中水印 */
			case 2:
				$x = (self::$info['width'] - $info[0])/2;
				$y = $margin;
				break;

			/* 右上角水印 */
			case 3 :
				$x = self::$info['width'] - $info[0] - $margin;
				$y = $margin;
				break;

			/* 左居中水印 */
			case 4 :
				$x = $margin;
				$y = (self::$info['height'] - $info[1])/2;
				break;

			/* 居中水印 */
			case 5 :
				$x = (self::$info['width'] - $info[0])/2;
				$y = (self::$info['height'] - $info[1])/2;
				break;

			/* 右居中水印 */
			case 6:
				$x = self::$info['width'] - $info[0] - $margin;
				$y = (self::$info['height'] - $info[1])/2;
				break;

			/* 左下角水印 */
			case 7 :
				$x = $margin;
				$y = self::$info['height'] - $info[1] - $margin;
				break;

			/* 下居中水印 */
			case 8 :
				$x = (self::$info['width'] - $info[0])/2;
				$y = self::$info['height'] - $info[1] - $margin;
				break;

			/* 右下角水印 */
			case 9:
				$x = self::$info['width'] - $info[0] - $margin;
				$y = self::$info['height'] - $info[1] - $margin;
				break;

			default :
				/* 自定义水印坐标 */
				if(is_array($locate)){
					list($x, $y) = $locate;
				} else {
					self::close();
					self::$error = array('status' => 5, 'message' => '不支持的水印位置类型');
					return false;
				}
		}

		do {
			//添加水印
			$src = imagecreatetruecolor($info[0], $info[1]);
			// 调整默认颜色
			$color = imagecolorallocate($src, 255, 255, 255);
			imagefill($src, 0, 0, $color);

			imagecopy($src, self::$image, 0, 0, $x, $y, $info[0], $info[1]);
			imagecopy($src, $water, 0, 0, 0, 0, $info[0], $info[1]);
			imagecopymerge(self::$image, $src, $x, $y, 0, 0, $info[0], $info[1], 100);

			//销毁零时图片资源
			imagedestroy($src);
		} while(!empty(self::$gif) && self::nextGif());

		//销毁水印资源
		imagedestroy($water);

		self::save($image);

		return self::$info;
	}

	 /**
	 * 图像添加文字
	 * @param  string  $text   添加的文字
	 * @param  string  $font   字体路径
	 * @param  integer $size   字号
	 * @param  string  $color  文字颜色
	 * @param  integer $locate 文字写入位置
	 * @param  integer $offset 文字相对当前位置的偏移量
	 * @param  integer $angle  文字倾斜角度
	 */
	public static function text($image, $font, $text, $size = 35, $color = '#FFFFFF', 
		$locate = 9, $offset = -20, $angle = 0){
		//资源检测
		if (!self::open($image)) {
			self::close();
			return false;
		}
		if (!is_file($font)) {
			self::close();
			self::$error = array('status' => 6, 'message' => '不存在的字体文件');
			return false;
		}

		//获取文字信息
		$info = imagettfbbox($size, $angle, $font, $text);
		$minx = min($info[0], $info[2], $info[4], $info[6]); 
		$maxx = max($info[0], $info[2], $info[4], $info[6]); 
		$miny = min($info[1], $info[3], $info[5], $info[7]); 
		$maxy = max($info[1], $info[3], $info[5], $info[7]); 

		/* 计算文字初始坐标和尺寸 */
		$x = $minx;
		$y = abs($miny);
		$w = $maxx - $minx;
		$h = $maxy - $miny;

		/* 设定文字位置 */
		switch ($locate) {
			/* 左上角文字 */
			case 1 :
				// 起始坐标即为左上角坐标，无需调整
				break;

			/* 上居中文字 */
			case 2 :
				$x += (self::$info['width'] - $w)/2;
				break;

			/* 右上角文字 */
			case 3 :
				$x += self::$info['width'] - $w;
				break;

			/* 左居中文字 */
			case 4 :
				$y += (self::$info['height'] - $h)/2;
				break;

			/* 居中文字 */
			case 5 :
				$x += (self::$info['width']  - $w)/2;
				$y += (self::$info['height'] - $h)/2;
				break;

			/* 右居中文字 */
			case 6 :
				$x += self::$info['width'] - $w;
				$y += (self::$info['height'] - $h)/2;
				break;
					
			/* 左下角文字 */
			case 7 :
				$y += self::$info['height'] - $h;
				break;
					
			/* 下居中文字 */
			case 8 :
				$x += (self::$info['width'] - $w)/2;
				$y += self::$info['height'] - $h;
				break;
					
			/* 右下角文字 */
			case 9 :
				$x += self::$info['width']  - $w;
				$y += self::$info['height'] - $h;
				break;

			default:
				/* 自定义文字坐标 */
				if(is_array($locate)){
					list($posx, $posy) = $locate;
					$x += $posx;
					$y += $posy;
				} else {
					self::close();
					self::$error = array('status' => 7, 'message' => '不支持的文字位置类型');
					return false;
				}
		}

		/* 设置偏移量 */
		if(is_array($offset)){
			$offset = array_map('intval', $offset);
			list($ox, $oy) = $offset;
		} else{
			$offset = intval($offset);
			$ox = $oy = $offset;
		}

		/* 设置颜色 */
		if(is_string($color) && 0 === strpos($color, '#')){
			$color = str_split(substr($color, 1), 2);
			$color = array_map('hexdec', $color);
			if(empty($color[3]) || $color[3] > 127){
				$color[3] = 0;
			}
		} elseif (!is_array($color)) {
			self::close();
			self::$error = array('status' => 8, 'message' => '错误的颜色值');
			return false;
		}

		do{
			/* 写入文字 */
			$col = imagecolorallocatealpha(self::$image, $color[0], $color[1], $color[2], $color[3]);
			imagettftext(self::$image, $size, $angle, $x + $ox, $y + $oy, $col, $font, $text);
		} while(!empty(self::$gif) && self::nextGif());

		self::save($image);

		return self::$info;
	}




	 /* 切换到GIF的下一帧并保存当前帧，内部使用 */
	private static function nextGif(){
		ob_start();
		ob_implicit_flush(0);
		imagegif(self::$image);
		$image = ob_get_clean();

		self::$gif->image($image);
		$next = self::$gif->nextImage();

		if($next){
			self::$image = imagecreatefromstring($next);
			return $next;
		} else {
			self::$image = imagecreatefromstring(self::$gif->image());
			return false;
		}
	}


	private static function save ($image, $interlace = true) {

		$type = self::$info['type'];
		//JPEG图像设置隔行扫描
		if ('jpeg' == $type || 'jpg' == $type) {
			$type = 'jpeg';
			imageinterlace(self::$image, $interlace);
		}

		//保存图像
		if('gif' == $type && !empty(self::$gif)){
			self::$gif->save($image);
		} else {
			$fun = 'image' . $type;
			$fun(self::$image, $image);
		}

		self::close();
	}

	private static function close () {
		empty(self::$image) || imagedestroy(self::$image);
	}

}


class GIF{
	/**
	 * GIF帧列表
	 * @var array
	 */
	private $frames = array();

	/**
	 * 每帧等待时间列表
	 * @var array
	 */
	private $delays = array();

	/**
	 * 构造方法，用于解码GIF图片
	 * @param string $src GIF图片数据
	 * @param string $mod 图片数据类型
	 */
	public function __construct($src = null, $mod = 'url') {
		if(!is_null($src)){
			if('url' == $mod && is_file($src)){
				$src = file_get_contents($src);
			}
			
			/* 解码GIF图片 */
			try{
				$de = new GIFDecoder($src);
				$this->frames = $de->GIFGetFrames();
				$this->delays = $de->GIFGetDelays();
			} catch(Exception $e){
				throw new Exception("解码GIF图片出错");
			}
		}
	}

	/**
	 * 设置或获取当前帧的数据
	 * @param  string $stream 二进制数据流
	 * @return boolean        获取到的数据
	 */
	public function image($stream = null){
		if(is_null($stream)){
			$current = current($this->frames);
			return false === $current ? reset($this->frames) : $current;
		} else {
			$this->frames[key($this->frames)] = $stream;
		}
	}

	/**
	 * 将当前帧移动到下一帧
	 * @return string 当前帧数据
	 */
	public function nextImage(){
		return next($this->frames);
	}

	/**
	 * 编码并保存当前GIF图片
	 * @param  string $gifname 图片名称
	 */
	public function save($gifname){
		$gif = new GIFEncoder($this->frames, $this->delays, 0, 2, 0, 0, 0, 'bin');
		file_put_contents($gifname, $gif->GetAnimation());
	}

}


/*
:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
::
::	GIFEncoder Version 2.0 by László Zsidi, http://gifs.hu
::
::	This class is a rewritten 'GifMerge.class.php' version.
::
::  Modification:
::   - Simplified and easy code,
::   - Ultra fast encoding,
::   - Built-in errors,
::   - Stable working
::
::
::	Updated at 2007. 02. 13. '00.05.AM'
::
::
::
::  Try on-line GIFBuilder Form demo based on GIFEncoder.
::
::  http://gifs.hu/phpclasses/demos/GifBuilder/
::
:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
*/

Class GIFEncoder {
	var $GIF = "GIF89a";		/* GIF header 6 bytes	*/
	var $VER = "GIFEncoder V2.05";	/* Encoder version		*/

	var $BUF = Array ( );
	var $LOP =  0;
	var $DIS =  2;
	var $COL = -1;
	var $IMG = -1;

	var $ERR = Array (
		'ERR00'=>"Does not supported function for only one image!",
		'ERR01'=>"Source is not a GIF image!",
		'ERR02'=>"Unintelligible flag ",
		'ERR03'=>"Does not make animation from animated GIF source",
	);

	/*
	:::::::::::::::::::::::::::::::::::::::::::::::::::
	::
	::	GIFEncoder...
	::
	*/
	function GIFEncoder	(
							$GIF_src, $GIF_dly, $GIF_lop, $GIF_dis,
							$GIF_red, $GIF_grn, $GIF_blu, $GIF_mod
						) {
		if ( ! is_array ( $GIF_src ) && ! is_array ( $GIF_tim ) ) {
			printf	( "%s: %s", $this->VER, $this->ERR [ 'ERR00' ] );
			exit	( 0 );
		}
		$this->LOP = ( $GIF_lop > -1 ) ? $GIF_lop : 0;
		$this->DIS = ( $GIF_dis > -1 ) ? ( ( $GIF_dis < 3 ) ? $GIF_dis : 3 ) : 2;
		$this->COL = ( $GIF_red > -1 && $GIF_grn > -1 && $GIF_blu > -1 ) ?
						( $GIF_red | ( $GIF_grn << 8 ) | ( $GIF_blu << 16 ) ) : -1;

		for ( $i = 0; $i < count ( $GIF_src ); $i++ ) {
			if ( strToLower ( $GIF_mod ) == "url" ) {
				$this->BUF [ ] = fread ( fopen ( $GIF_src [ $i ], "rb" ), filesize ( $GIF_src [ $i ] ) );
			}
			else if ( strToLower ( $GIF_mod ) == "bin" ) {
				$this->BUF [ ] = $GIF_src [ $i ];
			}
			else {
				printf	( "%s: %s ( %s )!", $this->VER, $this->ERR [ 'ERR02' ], $GIF_mod );
				exit	( 0 );
			}
			if ( substr ( $this->BUF [ $i ], 0, 6 ) != "GIF87a" && substr ( $this->BUF [ $i ], 0, 6 ) != "GIF89a" ) {
				printf	( "%s: %d %s", $this->VER, $i, $this->ERR [ 'ERR01' ] );
				exit	( 0 );
			}
			for ( $j = ( 13 + 3 * ( 2 << ( ord ( $this->BUF [ $i ] { 10 } ) & 0x07 ) ) ), $k = TRUE; $k; $j++ ) {
				switch ( $this->BUF [ $i ] { $j } ) {
					case "!":
						if ( ( substr ( $this->BUF [ $i ], ( $j + 3 ), 8 ) ) == "NETSCAPE" ) {
							printf	( "%s: %s ( %s source )!", $this->VER, $this->ERR [ 'ERR03' ], ( $i + 1 ) );
							exit	( 0 );
						}
						break;
					case ";":
						$k = FALSE;
						break;
				}
			}
		}
		GIFEncoder::GIFAddHeader ( );
		for ( $i = 0; $i < count ( $this->BUF ); $i++ ) {
			GIFEncoder::GIFAddFrames ( $i, $GIF_dly [ $i ] );
		}
		GIFEncoder::GIFAddFooter ( );
	}
	/*
	:::::::::::::::::::::::::::::::::::::::::::::::::::
	::
	::	GIFAddHeader...
	::
	*/
	function GIFAddHeader ( ) {
		$cmap = 0;

		if ( ord ( $this->BUF [ 0 ] { 10 } ) & 0x80 ) {
			$cmap = 3 * ( 2 << ( ord ( $this->BUF [ 0 ] { 10 } ) & 0x07 ) );

			$this->GIF .= substr ( $this->BUF [ 0 ], 6, 7		);
			$this->GIF .= substr ( $this->BUF [ 0 ], 13, $cmap	);
			$this->GIF .= "!\377\13NETSCAPE2.0\3\1" . GIFEncoder::GIFWord ( $this->LOP ) . "\0";
		}
	}
	/*
	:::::::::::::::::::::::::::::::::::::::::::::::::::
	::
	::	GIFAddFrames...
	::
	*/
	function GIFAddFrames ( $i, $d ) {

		$Locals_str = 13 + 3 * ( 2 << ( ord ( $this->BUF [ $i ] { 10 } ) & 0x07 ) );

		$Locals_end = strlen ( $this->BUF [ $i ] ) - $Locals_str - 1;
		$Locals_tmp = substr ( $this->BUF [ $i ], $Locals_str, $Locals_end );

		$Global_len = 2 << ( ord ( $this->BUF [ 0  ] { 10 } ) & 0x07 );
		$Locals_len = 2 << ( ord ( $this->BUF [ $i ] { 10 } ) & 0x07 );

		$Global_rgb = substr ( $this->BUF [ 0  ], 13,
							3 * ( 2 << ( ord ( $this->BUF [ 0  ] { 10 } ) & 0x07 ) ) );
		$Locals_rgb = substr ( $this->BUF [ $i ], 13,
							3 * ( 2 << ( ord ( $this->BUF [ $i ] { 10 } ) & 0x07 ) ) );

		$Locals_ext = "!\xF9\x04" . chr ( ( $this->DIS << 2 ) + 0 ) .
						chr ( ( $d >> 0 ) & 0xFF ) . chr ( ( $d >> 8 ) & 0xFF ) . "\x0\x0";

		if ( $this->COL > -1 && ord ( $this->BUF [ $i ] { 10 } ) & 0x80 ) {
			for ( $j = 0; $j < ( 2 << ( ord ( $this->BUF [ $i ] { 10 } ) & 0x07 ) ); $j++ ) {
				if	(
						ord ( $Locals_rgb { 3 * $j + 0 } ) == ( ( $this->COL >> 16 ) & 0xFF ) &&
						ord ( $Locals_rgb { 3 * $j + 1 } ) == ( ( $this->COL >>  8 ) & 0xFF ) &&
						ord ( $Locals_rgb { 3 * $j + 2 } ) == ( ( $this->COL >>  0 ) & 0xFF )
					) {
					$Locals_ext = "!\xF9\x04" . chr ( ( $this->DIS << 2 ) + 1 ) .
									chr ( ( $d >> 0 ) & 0xFF ) . chr ( ( $d >> 8 ) & 0xFF ) . chr ( $j ) . "\x0";
					break;
				}
			}
		}
		switch ( $Locals_tmp { 0 } ) {
			case "!":
				$Locals_img = substr ( $Locals_tmp, 8, 10 );
				$Locals_tmp = substr ( $Locals_tmp, 18, strlen ( $Locals_tmp ) - 18 );
				break;
			case ",":
				$Locals_img = substr ( $Locals_tmp, 0, 10 );
				$Locals_tmp = substr ( $Locals_tmp, 10, strlen ( $Locals_tmp ) - 10 );
				break;
		}
		if ( ord ( $this->BUF [ $i ] { 10 } ) & 0x80 && $this->IMG > -1 ) {
			if ( $Global_len == $Locals_len ) {
				if ( GIFEncoder::GIFBlockCompare ( $Global_rgb, $Locals_rgb, $Global_len ) ) {
					$this->GIF .= ( $Locals_ext . $Locals_img . $Locals_tmp );
				}
				else {
					$byte  = ord ( $Locals_img { 9 } );
					$byte |= 0x80;
					$byte &= 0xF8;
					$byte |= ( ord ( $this->BUF [ 0 ] { 10 } ) & 0x07 );
					$Locals_img { 9 } = chr ( $byte );
					$this->GIF .= ( $Locals_ext . $Locals_img . $Locals_rgb . $Locals_tmp );
				}
			}
			else {
				$byte  = ord ( $Locals_img { 9 } );
				$byte |= 0x80;
				$byte &= 0xF8;
				$byte |= ( ord ( $this->BUF [ $i ] { 10 } ) & 0x07 );
				$Locals_img { 9 } = chr ( $byte );
				$this->GIF .= ( $Locals_ext . $Locals_img . $Locals_rgb . $Locals_tmp );
			}
		}
		else {
			$this->GIF .= ( $Locals_ext . $Locals_img . $Locals_tmp );
		}
		$this->IMG  = 1;
	}
	/*
	:::::::::::::::::::::::::::::::::::::::::::::::::::
	::
	::	GIFAddFooter...
	::
	*/
	function GIFAddFooter ( ) {
		$this->GIF .= ";";
	}
	/*
	:::::::::::::::::::::::::::::::::::::::::::::::::::
	::
	::	GIFBlockCompare...
	::
	*/
	function GIFBlockCompare ( $GlobalBlock, $LocalBlock, $Len ) {

		for ( $i = 0; $i < $Len; $i++ ) {
			if	(
					$GlobalBlock { 3 * $i + 0 } != $LocalBlock { 3 * $i + 0 } ||
					$GlobalBlock { 3 * $i + 1 } != $LocalBlock { 3 * $i + 1 } ||
					$GlobalBlock { 3 * $i + 2 } != $LocalBlock { 3 * $i + 2 }
				) {
					return ( 0 );
			}
		}

		return ( 1 );
	}
	/*
	:::::::::::::::::::::::::::::::::::::::::::::::::::
	::
	::	GIFWord...
	::
	*/
	function GIFWord ( $int ) {

		return ( chr ( $int & 0xFF ) . chr ( ( $int >> 8 ) & 0xFF ) );
	}
	/*
	:::::::::::::::::::::::::::::::::::::::::::::::::::
	::
	::	GetAnimation...
	::
	*/
	function GetAnimation ( ) {
		return ( $this->GIF );
	}
}


/*
:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
::
::	GIFDecoder Version 2.0 by László Zsidi, http://gifs.hu
::
::	Created at 2007. 02. 01. '07.47.AM'
::
::
::
::
::  Try on-line GIFBuilder Form demo based on GIFDecoder.
::
::  http://gifs.hu/phpclasses/demos/GifBuilder/
::
:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
*/

Class GIFDecoder {
	var $GIF_buffer = Array ( );
	var $GIF_arrays = Array ( );
	var $GIF_delays = Array ( );
	var $GIF_stream = "";
	var $GIF_string = "";
	var $GIF_bfseek =  0;

	var $GIF_screen = Array ( );
	var $GIF_global = Array ( );
	var $GIF_sorted;
	var $GIF_colorS;
	var $GIF_colorC;
	var $GIF_colorF;

	/*
	:::::::::::::::::::::::::::::::::::::::::::::::::::
	::
	::	GIFDecoder ( $GIF_pointer )
	::
	*/
	function GIFDecoder ( $GIF_pointer ) {
		$this->GIF_stream = $GIF_pointer;

		GIFDecoder::GIFGetByte ( 6 );	// GIF89a
		GIFDecoder::GIFGetByte ( 7 );	// Logical Screen Descriptor

		$this->GIF_screen = $this->GIF_buffer;
		$this->GIF_colorF = $this->GIF_buffer [ 4 ] & 0x80 ? 1 : 0;
		$this->GIF_sorted = $this->GIF_buffer [ 4 ] & 0x08 ? 1 : 0;
		$this->GIF_colorC = $this->GIF_buffer [ 4 ] & 0x07;
		$this->GIF_colorS = 2 << $this->GIF_colorC;

		if ( $this->GIF_colorF == 1 ) {
			GIFDecoder::GIFGetByte ( 3 * $this->GIF_colorS );
			$this->GIF_global = $this->GIF_buffer;
		}
		/*
		 *
		 *  05.06.2007.
		 *  Made a little modification
		 *
		 *
		 -	for ( $cycle = 1; $cycle; ) {
		 +		if ( GIFDecoder::GIFGetByte ( 1 ) ) {
		 -			switch ( $this->GIF_buffer [ 0 ] ) {
		 -				case 0x21:
		 -					GIFDecoder::GIFReadExtensions ( );
		 -					break;
		 -				case 0x2C:
		 -					GIFDecoder::GIFReadDescriptor ( );
		 -					break;
		 -				case 0x3B:
		 -					$cycle = 0;
		 -					break;
		 -		  	}
		 -		}
		 +		else {
		 +			$cycle = 0;
		 +		}
		 -	}
		*/
		for ( $cycle = 1; $cycle; ) {
			if ( GIFDecoder::GIFGetByte ( 1 ) ) {
				switch ( $this->GIF_buffer [ 0 ] ) {
					case 0x21:
						GIFDecoder::GIFReadExtensions ( );
						break;
					case 0x2C:
						GIFDecoder::GIFReadDescriptor ( );
						break;
					case 0x3B:
						$cycle = 0;
						break;
				}
			}
			else {
				$cycle = 0;
			}
		}
	}
	/*
	:::::::::::::::::::::::::::::::::::::::::::::::::::
	::
	::	GIFReadExtension ( )
	::
	*/
	function GIFReadExtensions ( ) {
		GIFDecoder::GIFGetByte ( 1 );
		for ( ; ; ) {
			GIFDecoder::GIFGetByte ( 1 );
			if ( ( $u = $this->GIF_buffer [ 0 ] ) == 0x00 ) {
				break;
			}
			GIFDecoder::GIFGetByte ( $u );
			/*
			 * 07.05.2007.
			 * Implemented a new line for a new function
			 * to determine the originaly delays between
			 * frames.
			 *
			 */
			if ( $u == 4 ) {
				$this->GIF_delays [ ] = ( $this->GIF_buffer [ 1 ] | $this->GIF_buffer [ 2 ] << 8 );
			}
		}
	}
	/*
	:::::::::::::::::::::::::::::::::::::::::::::::::::
	::
	::	GIFReadExtension ( )
	::
	*/
	function GIFReadDescriptor ( ) {
		$GIF_screen	= Array ( );

		GIFDecoder::GIFGetByte ( 9 );
		$GIF_screen = $this->GIF_buffer;
		$GIF_colorF = $this->GIF_buffer [ 8 ] & 0x80 ? 1 : 0;
		if ( $GIF_colorF ) {
			$GIF_code = $this->GIF_buffer [ 8 ] & 0x07;
			$GIF_sort = $this->GIF_buffer [ 8 ] & 0x20 ? 1 : 0;
		}
		else {
			$GIF_code = $this->GIF_colorC;
			$GIF_sort = $this->GIF_sorted;
		}
		$GIF_size = 2 << $GIF_code;
		$this->GIF_screen [ 4 ] &= 0x70;
		$this->GIF_screen [ 4 ] |= 0x80;
		$this->GIF_screen [ 4 ] |= $GIF_code;
		if ( $GIF_sort ) {
			$this->GIF_screen [ 4 ] |= 0x08;
		}
		$this->GIF_string = "GIF87a";
		GIFDecoder::GIFPutByte ( $this->GIF_screen );
		if ( $GIF_colorF == 1 ) {
			GIFDecoder::GIFGetByte ( 3 * $GIF_size );
			GIFDecoder::GIFPutByte ( $this->GIF_buffer );
		}
		else {
			GIFDecoder::GIFPutByte ( $this->GIF_global );
		}
		$this->GIF_string .= chr ( 0x2C );
		$GIF_screen [ 8 ] &= 0x40;
		GIFDecoder::GIFPutByte ( $GIF_screen );
		GIFDecoder::GIFGetByte ( 1 );
		GIFDecoder::GIFPutByte ( $this->GIF_buffer );
		for ( ; ; ) {
			GIFDecoder::GIFGetByte ( 1 );
			GIFDecoder::GIFPutByte ( $this->GIF_buffer );
			if ( ( $u = $this->GIF_buffer [ 0 ] ) == 0x00 ) {
				break;
			}
			GIFDecoder::GIFGetByte ( $u );
			GIFDecoder::GIFPutByte ( $this->GIF_buffer );
		}
		$this->GIF_string .= chr ( 0x3B );
		/*
			 Add frames into $GIF_stream array...
		*/
		$this->GIF_arrays [ ] = $this->GIF_string;
	}
	/*
	:::::::::::::::::::::::::::::::::::::::::::::::::::
	::
	::	GIFGetByte ( $len )
	::
	*/

	/*
	 *
	 *  05.06.2007.
	 *  Made a little modification
	 *
	 *
	 -	function GIFGetByte ( $len ) {
	 -		$this->GIF_buffer = Array ( );
	 -
	 -		for ( $i = 0; $i < $len; $i++ ) {
	 +			if ( $this->GIF_bfseek > strlen ( $this->GIF_stream ) ) {
	 +				return 0;
	 +			}
	 -			$this->GIF_buffer [ ] = ord ( $this->GIF_stream { $this->GIF_bfseek++ } );
	 -		}
	 +		return 1;
	 -	}
	 */
	function GIFGetByte ( $len ) {
		$this->GIF_buffer = Array ( );

		for ( $i = 0; $i < $len; $i++ ) {
			if ( $this->GIF_bfseek > strlen ( $this->GIF_stream ) ) {
				return 0;
			}
			$this->GIF_buffer [ ] = ord ( $this->GIF_stream { $this->GIF_bfseek++ } );
		}
		return 1;
	}
	/*
	:::::::::::::::::::::::::::::::::::::::::::::::::::
	::
	::	GIFPutByte ( $bytes )
	::
	*/
	function GIFPutByte ( $bytes ) {
		for ( $i = 0; $i < count ( $bytes ); $i++ ) {
			$this->GIF_string .= chr ( $bytes [ $i ] );
		}
	}
	/*
	:::::::::::::::::::::::::::::::::::::::::::::::::::
	::
	::	PUBLIC FUNCTIONS
	::
	::
	::	GIFGetFrames ( )
	::
	*/
	function GIFGetFrames ( ) {
		return ( $this->GIF_arrays );
	}
	/*
	:::::::::::::::::::::::::::::::::::::::::::::::::::
	::
	::	GIFGetDelays ( )
	::
	*/
	function GIFGetDelays ( ) {
		return ( $this->GIF_delays );
	}
}


?>