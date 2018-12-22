<?php

namespace App\Traits;

use App\Events\EventTrigger;
use App\Transcoder;
use App\Tv;
use App\Video;
use FFMpeg\Format\Video\X264;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Pbmedia\LaravelFFMpeg\FFMpegFacade as FFMpeg;
use Symfony\Component\Process\Process;

trait FFmpegTranscoding
{
    public $number = 0;
    public $id;
    public $Watermark = null;
    public $message = null;
    public $tmdb_id;

    public function transcodingToHLS($tempPathVideo, $resolution, $id, $name, $message, $tmdb_id)
    {

        $this->id = $id;
        $this->message = $message;
        $this->tmdb_id = $tmdb_id;

        // Bitrate List
        $cLowBitrate = null;
        $cMidBitrate = null;
        $cHighBitrate = null;
        $cVeryHighBitrate = null;
        $cUltreHighBitrate = null;

        // Create Directory
        Storage::makeDirectory('/public/videos/' . $this->id . '/nonenc');

        $lowBitrate = (new X264('aac', 'libx264'))->setKiloBitrate(400);
        $midBitrate = (new X264('aac', 'libx264'))->setKiloBitrate(700);
        $highBitrate = (new X264('aac', 'libx264'))->setKiloBitrate(1500);
        $veryHighBitrate = (new X264('aac', 'libx264'))->setKiloBitrate(3000);
        $ultraHighBitrate = (new X264('aac', 'libx264'))->setKiloBitrate(10000);

        $convert = FFMpeg::fromDisk('public')
            ->open($tempPathVideo)
            ->exportForHLS()
            ->onProgress(function ($percentage) {
                if (ctype_digit(strval($percentage))) {
                    if ($percentage > $this->number && $percentage != $this->number) {
                        $this->number = $percentage + 1;
                        event(new EventTrigger(['message' => 'Video Convert To HLS Playlist Wait,  its take time', 'progress' => $percentage, 'tmdb_id' => $this->tmdb_id]));
                    }
                }
            })->toDisk('public');

        foreach ($resolution as $key => $value) {
            if ($value['Resolution'] === '4k') {
                $convert->addFormat($ultraHighBitrate);
                $cUltreHighBitrate = true;
            }
            if ($value['Resolution'] === '1080') {
                $convert->addFormat($HighBitrate);
                $cVeryHighBitrate = true;
            }
            if ($value['Resolution'] === '720') {
                $convert->addFormat($highBitrate);
                $cHighBitrate = true;
            }
            if ($value['Resolution'] === '480') {
                $convert->addFormat($midBitrate);
                $cMidBitrate = true;
            }
            if ($value['Resolution'] === '320') {
                $convert->addFormat($lowBitrate);
                $cLowBitrate = true;
            }
        }

        $convert->save('videos/' . $id . '/nonenc/' . $name . '.m3u8');


        // Encrypted With AES-128 KEY
        $this->encTsFiles($id);

        // Modif m3u8 file
        $this->modM3U8File($cLowBitrate, $cMidBitrate, $cHighBitrate, $cVeryHighBitrate, $cUltreHighBitrate, $name, $id);


        // Delete folder
        Storage::deleteDirectory('public/videos/' . $id . '/nonenc');

        return true;

    }

    public function encTsFiles($id)
    {

        $directory1 = storage_path('/app/public/videos/' . $id . '/nonenc/');

        foreach (glob($directory1 . '*.ts') as $url) {


            $strPath = substr($url, strrpos($url, '/') + 1);
            $orjName = strtok($strPath, '.');

            // create key to each segment
            $keyName = $orjName . '.key';
            $keyFilePath = '"' . storage_path('/app/public/videos/' . $id . '/' . $keyName) . '"';
            $key = exec("openssl rand 16 >". $keyFilePath);


            // Create iv
            $iv = exec("hexdump -v -e '16/1 \"%02x\"' $keyFilePath");
            $outputPath =  '"' . storage_path('/app/public/videos/' . $id . '/' . $orjName . '.ts.enc') . '"';
            $urlSp = '"' . $url . '"';
            $encrypted = exec("openssl aes-128-cbc -e -in $urlSp -out $outputPath -nosalt -iv $iv -K $iv");

        }


    }

    public function modM3U8File($lowBitrate, $midBitrate, $highBitrate, $veryHighBitrate, $ultreHighBitrate, $name, $id)
    {
        $directory2 = storage_path('/app/public/videos/' . $id . '/');


        // Move Master Playlist File
        $moveFile = File::move( storage_path('/app/public/videos/' . $id . '/nonenc/'. $name.'.m3u8'),  storage_path('/app/public/videos/' . $id . '/'. $name.'.m3u8'));

        // Add RESOLUTION
        if($moveFile) {


            if (!is_null($lowBitrate)) {
                $getMasPlaylist = File::get(storage_path('/app/public/videos/' . $id . '/'. $name.'.m3u8'));
                $strUriReplice = str_replace("BANDWIDTH=400000", "BANDWIDTH=400000,RESOLUTION=640x360", $getMasPlaylist);
                $putChange = File::put(storage_path('/app/public/videos/' . $id . '/' . $name . '.m3u8'), $strUriReplice);
            }
            if (!is_null($midBitrate)) {
                $getMasPlaylist = File::get(storage_path('/app/public/videos/' . $id . '/'. $name.'.m3u8'));
                $strUriReplice = str_replace("BANDWIDTH=700000", "BANDWIDTH=700000,RESOLUTION=854x480", $getMasPlaylist);
                $putChange = File::put(storage_path('/app/public/videos/' . $id . '/' . $name . '.m3u8'), $strUriReplice);
            }
            if (!is_null($highBitrate)) {
                $getMasPlaylist = File::get(storage_path('/app/public/videos/' . $id . '/'. $name.'.m3u8'));
                $strUriReplice = str_replace("BANDWIDTH=1500000", "BANDWIDTH=1500000,RESOLUTION=1280x720", $getMasPlaylist);
                $putChange = File::put(storage_path('/app/public/videos/' . $id . '/' . $name . '.m3u8'), $strUriReplice);
            }
            if (!is_null($veryHighBitrate)) {
                $getMasPlaylist = File::get(storage_path('/app/public/videos/' . $id . '/'. $name.'.m3u8'));
                $strUriReplice = str_replace("BANDWIDTH=3000000", "BANDWIDTH=3000000,RESOLUTION=1920x1080", $getMasPlaylist);
                $putChange = File::put(storage_path('/app/public/videos/' . $id . '/' . $name . '.m3u8'), $strUriReplice);
            }
            if (!is_null($ultreHighBitrate)) {
                $getMasPlaylist = File::get(storage_path('/app/public/videos/' . $id . '/'. $name.'.m3u8'));
                $strUriReplice = str_replace("BANDWIDTH=10000000", "BANDWIDTH=10000000,RESOLUTION=3840x2160", $getMasPlaylist);
                $putChange = File::put(storage_path('/app/public/videos/' . $id . '/' . $name . '.m3u8'), $strUriReplice);
            }
        }


        // 360P
        if (!is_null($lowBitrate)) {

            $getFile = File::get(storage_path('/app/public/videos/' . $id . '/nonenc/' . $name . '_400.m3u8'));
            $strUriReplice = explode("\n", $getFile);

            foreach (glob($directory2 . '*.key') as $url) {
                $keyName = substr($url, strrpos($url, '/') + 1);
                $orjTsName = strtok($keyName, '.') . '.ts';

                foreach ($strUriReplice as $index => $key) {

                    if ($orjTsName == $key) {
                        $l4 = $strUriReplice[$index - 1];
                        $strUriReplice[$index - 1] = "#EXT-X-KEY:METHOD=AES-128,URI=\"$keyName\",iv=" . $index  . "\n" . $l4;
                        $strUriReplice[$index] = $orjTsName .'.enc';
                    }

                }

            }

            $outFile = implode("\n", $strUriReplice);
            File::put(storage_path('/app/public/videos/' . $id . '/' . $name . '_400.m3u8'), $outFile);

        }

        // 480P
        if (!is_null($midBitrate)) {
            $getFile = File::get(storage_path('/app/public/videos/' . $id . '/nonenc/' . $name . '_700.m3u8'));
            $strUriReplice = explode("\n", $getFile);

            foreach (glob($directory2 . '*.key') as $url) {
                $keyName = substr($url, strrpos($url, '/') + 1);
                $orjTsName = strtok($keyName, '.') .  '.ts';

                foreach ($strUriReplice as $index => $key) {

                    if ($orjTsName == $key) {
                        $l4 = $strUriReplice[$index - 1];
                        $strUriReplice[$index - 1] = "#EXT-X-KEY:METHOD=AES-128,URI=\"$keyName\",iv=" . $index  . "\n" . $l4;
                        $strUriReplice[$index] = $orjTsName .'.enc';

                    }

                }

            }

            $outFile = implode("\n", $strUriReplice);
            File::put(storage_path('/app/public/videos/' . $id . '/' . $name . '_700.m3u8'), $outFile);

        }

        // 720P
        if (!is_null($highBitrate)) {
            $getFile = File::get(storage_path('/app/public/videos/' . $id . '/nonenc/' . $name . '_1500.m3u8'));
            $strUriReplice = explode("\n", $getFile);

            foreach (glob($directory2 . '*.key') as $url) {
                $keyName = substr($url, strrpos($url, '/') + 1);
                $orjTsName = strtok($keyName, '.') .  '.ts';

                foreach ($strUriReplice as $index => $key) {

                    if ($orjTsName == $key) {
                        $l4 = $strUriReplice[$index - 1];
                        $strUriReplice[$index - 1] = "#EXT-X-KEY:METHOD=AES-128,URI=\"$keyName\",iv=" . $index  . "\n" . $l4;
                        $strUriReplice[$index] = $orjTsName .'.enc';

                    }

                }

            }
            $outFile = implode("\n", $strUriReplice);
            File::put(storage_path('/app/public/videos/' . $id . '/' . $name . '_1500.m3u8'), $outFile);
        }

        // 1080P
        if (!is_null($veryHighBitrate)) {
            $getFile = File::get(storage_path('/app/public/videos/' . $id . '/nonenc/' . $name . '_3000.m3u8'));
            $strUriReplice = explode("\n", $getFile);

            foreach (glob($directory2 . '*.key') as $url) {
                $keyName = substr($url, strrpos($url, '/') + 1);
                $orjTsName = strtok($keyName, '.') .  '.ts';

                foreach ($strUriReplice as $index => $key) {
                    if ($orjTsName == $key) {
                        $l4 = $strUriReplice[$index - 1];
                        $strUriReplice[$index - 1] = "#EXT-X-KEY:METHOD=AES-128,URI=\"$keyName\",iv=" . $index  . "\n" . $l4;
                        $strUriReplice[$index] = $orjTsName .'.enc';
                    }

                }

            }
            $outFile = implode("\n", $strUriReplice);
            File::put(storage_path('/app/public/videos/' . $id . '/' . $name . '_3000.m3u8'), $outFile);
        }

        // 4K
        if (!is_null($ultreHighBitrate)) {
            $getFile = File::get(storage_path('/app/public/videos/' . $id . '/nonenc/' . $name . '_10000.m3u8'));
            $strUriReplice = explode("\n", $getFile);

            foreach (glob($directory2 . '*.key') as $url) {
                $keyName = substr($url, strrpos($url, '/') + 1);
                $orjTsName = strtok($keyName, '.') .  '.ts';

                foreach ($strUriReplice as $index => $key) {
                    if ($orjTsName == $key) {
                        $l4 = $strUriReplice[$index - 1];
                        $strUriReplice[$index - 1] = "#EXT-X-KEY:METHOD=AES-128,URI=\"$keyName\"" . "\n" . $l4;
                        $strUriReplice[$index] = $orjTsName .'.enc';

                    }

                }

            }
            $outFile = implode("\n", $strUriReplice);
            File::put(storage_path('/app/public/videos/' . $id . '/' . $name . '_10000.m3u8'), $outFile);
        }

    }

    public function replaceUriPath($lowBitrate, $midBitrate, $highBitrate, $veryHighBitrate, $id, $name)
    {

        // Put files
        $oldPath = storage_path('/app/public/' . $id . '/' . $name . '.key');
        $newPath = $name . '.key';

        if (!is_null($lowBitrate)) {
            $getFile = File::get(storage_path('/app/public/' . $id . '/' . $name . '_360.m3u8'));
            $strUriReplice = str_replace("$oldPath", "$newPath", $getFile);
            $createFileInfo = File::put(storage_path('/app/public/' . $id . '/' . $name . '_360.m3u8'), $strUriReplice);

        }
        if (!is_null($midBitrate)) {
            $getFile = File::get(storage_path('/app/public/' . $id . '/' . $name . '_480.m3u8'));
            $strUriReplice = str_replace("$oldPath", "$newPath", $getFile);
            $createFileInfo = File::put(storage_path('/app/public/' . $id . '/' . $name . '_480.m3u8'), $strUriReplice);

        }
        if (!is_null($highBitrate)) {
            $getFile = File::get(storage_path('/app/public/' . $id . '/' . $name . '_720.m3u8'));
            $strUriReplice = str_replace("$oldPath", "$newPath", $getFile);
            $createFileInfo = File::put(storage_path('/app/public/' . $id . '/' . $name . '_720.m3u8'), $strUriReplice);

        }
        if (!is_null($veryHighBitrate)) {
            $getFile = File::get(storage_path('/app/public/' . $id . '/' . $name . '_1080.m3u8'));
            $strUriReplice = str_replace("$oldPath", "$newPath", $getFile);
            $createFileInfo = File::put(storage_path('/app/public/' . $id . '/' . $name . '_1080.m3u8'), $strUriReplice);

        }

        return true;

    }

    public function checkWaterMarkHLS()
    {

        // Check if there is watermark

        $watermark = Transcoder::first()->watermark_url;
        $position = Transcoder::first()->watermark_position;

        if ($watermark !== null && !empty($watermark)) {

            if ($position == 'TopLeft') {
                $this->Watermark = ' -i ' . storage_path('/app/public/watermark/' . $watermark) . ' -filter_complex ' . '"overlay=30:30" ';
            } elseif ($position == 'TopRight') {
                $this->Watermark = ' -i ' . storage_path('/app/public/watermark/' . $watermark) . ' -filter_complex ' . '"overlay=main_w-overlay_w-30:30" ';
            } elseif ($position == 'BottomLeft') {
                $this->Watermark = ' -i ' . storage_path('/app/public/watermark/' . $watermark) . ' -filter_complex ' . '"overlay=30:main_h-overlay_h" ';
            } elseif ($position == 'BottomRight') {
                $this->Watermark = ' -i ' . storage_path('/app/public/watermark/' . $watermark) . ' -filter_complex ' . '"overlay=main_w-overlay_w-30:main_h-overlay_h-30" ';
            }
        }

    }

    public function transcodeVideoToMp4($resolution, $id, $path, $tmdb_id, $cloud, $item_name)
    {


        // Check Cloud Disl
        if($cloud === 'aws'){
            $output_path = '/videos/' . $item_name;
            $cloud_name = 's3_private';
        }else {
            $output_path = '/storage/videos/' . $item_name;
            $cloud_name = 'public';
        }

        $this->tmdb_id = $tmdb_id;

        $this->checkWaterMarkMP4();

        $lowBitrate = (new X264('aac', 'libx264'))->setKiloBitrate(400);
        $midBitrate = (new X264('aac', 'libx264'))->setKiloBitrate(700);
        $highBitrate = (new X264('aac', 'libx264'))->setKiloBitrate(1500);
        $HighBitrate = (new X264('aac', 'libx264'))->setKiloBitrate(3000);
        $ultraHighBitrate = (new X264('aac', 'libx264'))->setKiloBitrate(10000);

        foreach ($resolution as $key => $value) {

            if($value['Resolution'] === '4k') {
                $this->number = 0;

                // Progress
                $ultraHighBitrate->on('progress', function ($convertMid, $ultraHighBitrate, $percentage) {
                    if (ctype_digit(strval($percentage))) {
                        if ($percentage > $this->number) {
                            $this->number = $percentage + 1;
                            event(new EventTrigger(['message' => 'Video Convert To 480,  its take time', 'progress' => $percentage]));
                        }
                    }
                });

                $newNameMP4 = str_random(20) . '-2160.mp4';
                $convertUltra = FFMpeg::fromDisk('public')
                    ->open($path)
                    ->addFilter(function ($filters) {
                        if ($this->Watermark !== null) {
                            $filters->watermark(storage_path('/app/public/watermark/' . Transcoder::first()->watermark_url), $this->Watermark);
                        }
                    })
                    ->export()
                    ->toDisk($cloud_name)
                    ->inFormat($ultraHighBitrate)
                    ->save('videos/' . $item_name . '/' . $newNameMP4);

                // Store video data
                $video = new Video();
                $video->movie_id = $id;
                $video->resolution = '4k';
                $video->video_url = $output_path . '/' . $newNameMP4;
                $video->save();
            }
            if ($value['Resolution'] === '1080') {
                $this->number = 0;

                // Progress
                $HighBitrate->on('progress', function ($converVeryHigh, $HighBitrate, $percentage) {
                    if (ctype_digit(strval($percentage))) {
                        if ($percentage > $this->number) {
                            $this->number = $percentage + 1;
                            event(new EventTrigger(['message' => 'Video Convert To 1080,  its take time', 'progress' => $percentage, 'tmdb_id' => $this->tmdb_id]));
                        }
                    }
                });
                $newNameMP4 = str_random(20) . '-1080.mp4';
                $converVeryHigh = FFMpeg::fromDisk('public')
                    ->open($path)
                    ->addFilter(function ($filters) {
                        if ($this->Watermark !== null) {
                            $filters->watermark(storage_path('/app/public/watermark/' . Transcoder::first()->watermark_url), $this->Watermark);
                        }
                    })
                    ->export()
                    ->toDisk($cloud_name)
                    ->inFormat($HighBitrate)
                    ->save('videos/' . $item_name . '/' . $newNameMP4);

                // Store video data
                $video = new Video();
                $video->movie_id = $id;
                $video->resolution = '1080';
                $video->video_url = $output_path . '/' . $newNameMP4;
                $video->save();
            }
            if ($value['Resolution'] === '720') {
                $this->number = 0;

                // Progress
                $highBitrate->on('progress', function ($convertHigh, $highBitrate, $percentage) {
                    if (ctype_digit(strval($percentage))) {
                        if ($percentage > $this->number) {
                            $this->number = $percentage + 1;
                            event(new EventTrigger(['message' => 'Video Convert To 720,  its take time', 'progress' => $percentage, 'tmdb_id' => $this->tmdb_id]));
                        }
                    }
                });

                $newNameMP4 = str_random(20) . '-720.mp4';
                $convertHigh = FFMpeg::fromDisk('public')
                    ->open($path)
                    ->addFilter(function ($filters) {
                        if ($this->Watermark !== null) {
                            $filters->watermark(storage_path('/app/public/watermark/' . Transcoder::first()->watermark_url), $this->Watermark);
                        }
                    })
                    ->export()
                    ->toDisk($cloud_name)
                    ->inFormat($highBitrate)
                    ->save('videos/' . $item_name . '/' . $newNameMP4);

                // Store video data
                $video = new Video();
                $video->movie_id = $id;
                $video->resolution = '720';
                $video->video_url = $output_path . '/' . $newNameMP4;
                $video->save();
            }
            if ($value['Resolution'] === '480') {
                $this->number = 0;

                // Progress
                $midBitrate->on('progress', function ($convertMid, $midBitrate, $percentage) {
                    if (ctype_digit(strval($percentage))) {
                        if ($percentage > $this->number) {
                            $this->number = $percentage + 1;
                            event(new EventTrigger(['message' => 'Video Convert To 480,  its take time', 'progress' => $percentage, 'tmdb_id' => $this->tmdb_id]));
                        }
                    }
                });

                $newNameMP4 = str_random(20) . '-480.mp4';
                $convertMid = FFMpeg::fromDisk('public')
                    ->open($path)
                    ->addFilter(function ($filters) {
                        if ($this->Watermark !== null) {
                            $filters->watermark(storage_path('/app/public/watermark/' . Transcoder::first()->watermark_url), $this->Watermark);
                        }
                    })
                    ->export()
                    ->toDisk($cloud_name)
                    ->inFormat($midBitrate)
                    ->save('videos/' . $item_name . '/' . $newNameMP4);

                // Store video data
                $video = new Video();
                $video->movie_id = $id;
                $video->resolution = '480';
                $video->video_url = $output_path. '/' . $newNameMP4;
                $video->save();
            }
            if ($value['Resolution'] === '320') {
                $this->number = 0;

                // Progress
                $lowBitrate->on('progress', function ($convertLow, $lowBitrate, $percentage) {
                    if (ctype_digit(strval($percentage))) {
                        if ($percentage > $this->number) {
                            $this->number = $percentage + 1;
                            event(new EventTrigger(['message' => 'Video Convert To 320,  its take time', 'progress' => $percentage, 'tmdb_id' => $this->tmdb_id]));
                        }
                    }
                });

                $newNameMP4 = str_random(20) . '-320.mp4';
                $convertLow = FFMpeg::fromDisk('public')
                    ->open($path)
                    ->addFilter(function ($filters) {
                        if ($this->Watermark !== null) {
                            $filters->watermark(storage_path('/app/public/watermark/' . Transcoder::first()->watermark_url), $this->Watermark);
                        }
                    })
                    ->export()
                    ->toDisk($cloud_name)
                    ->inFormat($lowBitrate)
                    ->save('videos/' . $item_name . '/' . $newNameMP4);

                // Store video data
                $video = new Video();
                $video->movie_id = $id;
                $video->resolution = '320';
                $video->video_url = $output_path . '/' . $newNameMP4;
                $video->save();
            }
        }

        return true;
    }

    public function checkWaterMarkMP4()
    {

        // Check if there is watermark

        $watermark = Transcoder::first()->watermark_url;
        $position = Transcoder::first()->watermark_position;

        if ($watermark !== null && !empty($watermark)) {

            if ($position === 'TopLeft') {
                $this->Watermark = ['position' => 'relative', 'top' => 30, 'left' => 30];
            } elseif ($position === 'TopRight') {
                $this->Watermark = ['position' => 'relative', 'top' => 30, 'right' => 30];
            } elseif ($position === 'BottomLeft') {
                $this->Watermark = ['position' => 'relative', 'bottom' => 30, 'left' => 30];
            } elseif ($position === 'BottomLeft') {
                $this->Watermark = ['position' => 'relative', 'bottom' => 30, 'right' => 30];
            }
        }

    }

    public function getProgressFFmpeg($path, $buffer)
    {
        $logFile = File::append($path, $buffer);
        $content = @file_get_contents($path);

        //get duration of source
        preg_match("/Duration: (.*?), start:/", $content, $matches);

        if (count($matches) > 0) {
            $rawDuration = $matches[1];

            //rawDuration is in 00:00:00.00 format. This converts it to seconds.
            $ar = array_reverse(explode(":", $rawDuration));
            $duration = floatval($ar[0]);
            if (!empty($ar[1])) {
                $duration += intval($ar[1]) * 60;
            }

            if (!empty($ar[2])) {
                $duration += intval($ar[2]) * 60 * 60;
            }

            //get the time in the file that is already encoded
            preg_match_all("/time=(.*?) bitrate/", $content, $matches);

            $rawTime = array_pop($matches);

            //this is needed if there is more than one match
            if (is_array($rawTime)) {
                $rawTime = array_pop($rawTime);
            }

            //rawTime is in 00:00:00.00 format. This converts it to seconds.
            $ar = array_reverse(explode(":", $rawTime));
            $time = floatval($ar[0]);
            if (!empty($ar[1])) {
                $time += intval($ar[1]) * 60;
            }

            if (!empty($ar[2])) {
                $time += intval($ar[2]) * 60 * 60;
            }

            //calculate the progress
            $progress = round(($time / $duration) * 100);

            return ['duration' => $duration, 'current' => $time, 'progress' => $progress];
        }
    }

    /**
     * Live Tv
     *
     * @param [type] $resolution
     * @param [type] $link
     * @param [type] $id
     * @param [type] $name
     * @return void
     */
    public function transcodingToLiveHLS($resolution, $link, $id, $name)
    {
        $this->id = $id;

        // Bitrate List
        $lowBitrate = null;
        $midBitrate = null;
        $highBitrate = null;
        $veryHighBitrate = null;


        if ($resolution === '1080') {
            $veryHighBitrate = ' ffmpeg -i ' . $link . ' -c:a aac -ar 48000 -c:v h264 -profile:v main -sc_threshold 0 -g 48  -hls_time 4 -hls_flags delete_segments -b:v 5000k -maxrate 5350k -bufsize 7500k -b:a 192k ' . storage_path('app/public/iptv/' . $this->id . '/' . $name );
        }
        if ($resolution === '720') {
            $highBitrate = ' ffmpeg -i ' . $link . ' -c:a aac -ar 48000 -c:v h264 -profile:v main -sc_threshold 0 -g 48  -hls_time 4 -hls_flags delete_segments -b:v 2800k -maxrate 2996k -bufsize 4200k -b:a 128 ' . storage_path('app/public/iptv/' . $this->id . '/' . $name );
        }
        if ($resolution === '480') {
            $midBitrate = ' ffmpeg -i ' . $link . ' -c:a aac -ar 48000 -c:v h264 -profile:v main -sc_threshold 0 -g 48  -hls_time 4 -hls_flags delete_segments -b:v 1400k -maxrate 1498k -bufsize 2100k -b:a 128 ' . storage_path('app/public/iptv/' . $this->id . '/' . $name );
        }
        if ($resolution === '360') {
            $lowBitrate = ' ffmpeg -i ' . $link . ' -c:a aac -ar 48000 -c:v h264 -profile:v main -sc_threshold 0 -g 48  -hls_time 4 -hls_flags delete_segments  -b:v 800k -maxrate 856k -bufsize 1200k -b:a 96k ' . storage_path('app/public/iptv/' . $this->id . '/' . $name );
        }

        // Set FFmpeg Command
        $ffmpegCommand =
            (is_null($veryHighBitrate) ? ' ' : $veryHighBitrate) .
            (is_null($highBitrate) ? ' ' : $highBitrate) .
            (is_null($midBitrate) ? ' ' : $midBitrate) .
            (is_null($lowBitrate) ? ' ' : $lowBitrate);

        $pid = exec($ffmpegCommand . ' > /dev/null 2>&1 & echo $!; ', $output);

        Log::info($ffmpegCommand);

        // Create Master Playlist Before FFmepg run
        $createMasterPlaylist = $this->createMasterPlaylistLive($lowBitrate, $midBitrate, $highBitrate, $veryHighBitrate, 'iptv/' . $id, $name);

        if (!$createMasterPlaylist) {
            return response()->json(['status' => 'failed', 'message' => 'Error to create master playlist file'], 422);
        }

        // Store PID
        $check = Tv::find($id);
        $check->streaming_status = 1;
        $check->streaming_pid = $pid;
        $check->save();

        return true;

    }

    public function replaceUriPathLive($lowBitrate, $midBitrate, $highBitrate, $veryHighBitrate, $id, $name)
    {

        // Put files
        $oldPath = storage_path('/app/public/' . $id . '/' . $name . '.key');
        $newPath = $name . '.key';


        if (!is_null($lowBitrate)) {
            $getFile = File::get(storage_path('/app/public/' . $id . '/' . $name . '.key'));
            $strUriReplice = str_replace("$oldPath", "$newPath", $getFile);
            $createFileInfo = File::put(storage_path('/app/public/' . $id . '/' . $name . '.key'), $strUriReplice);

        }
        if (!is_null($midBitrate)) {
            $getFile = File::get(storage_path('/app/public/' . $id . '/' . $name . '.key'));
            $strUriReplice = str_replace("$oldPath", "$newPath", $getFile);
            $createFileInfo = File::put(storage_path('/app/public/' . $id . '/' . $name . '.key'), $strUriReplice);

        }
        if (!is_null($highBitrate)) {
            $getFile = File::get(storage_path('/app/public/' . $id . '/' . $name . '.key'));
            $strUriReplice = str_replace("$oldPath", "$newPath", $getFile);
            $createFileInfo = File::put(storage_path('/app/public/' . $id . '/' . $name . '.key'), $strUriReplice);

        }
        if (!is_null($veryHighBitrate)) {
            $getFile = File::get(storage_path('/app/public/' . $id . '/' . $name . '.key'));
            $strUriReplice = str_replace("$oldPath", "$newPath", $getFile);
            $createFileInfo = File::put(storage_path('/app/public/' . $id . '/' . $name . '.key'), $strUriReplice);

        }

        return true;

    }

    public function createMasterPlaylistLive($lowBitrate, $midBitrate, $highBitrate, $veryHighBitrate, $id, $name)
    {

        $line = '#EXTM3U' . "\n" . '#EXT-X-VERSION:3' . "\n " . '#EXT-X-MEDIA-SEQUENCE:0' . "\n" . '#EXT-X-ALLOW-CACHE:NO' . "\n" . '#EXT-X-TARGETDURATION:2' . "\n";
        if (!is_null($lowBitrate)) {
            $line .= '#EXTINF:2.000000,' . "\n";
            $line .= $name . '.ts' . "\n";
        }
        if (!is_null($midBitrate)) {
            $line .= '#EXTINF:2.000000,' . "\n";
            $line .= $name . '.ts' . "\n";
        }
        if (!is_null($highBitrate)) {
            $line .= '#EXTINF:2.000000,' . "\n";
            $line .= $name . '.ts' . "\n";
        }
        if (!is_null($veryHighBitrate)) {
            $line .= '#EXTINF:2.000000,' . "\n";
            $line .= $name . '.ts' . "\n";
        }

        // Create File
        $createFile = File::put(storage_path('/app/public/' . $id . '/' . $name), $line);
        if ($createFile) {
            return true;
        } else {
            return false;
        }
    }

}
