<?php

namespace App\Http\Controllers\Users;

use App\Episode;
use App\Helpers;
use App\Http\Controllers\Controller;
use App\Movie;
use App\Recently_watched;
use App\Report;
use App\Series;
use App;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Aws\CloudFront\CloudFrontClient;


class VideoPlayerController extends Controller
{

    /**
     * This Constructer check if the user is make payment or not if not it will return 404
     *
     * VideoPlayerController constructor.
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            Helpers::checkUserPayment(Auth::user());

            return $next($request);
        });
    }


    /**
     * Undocumented function
     *
     * @param [type] $id
     * @return void
     */
    public function getMovieVideo($id)
    {
        // Check this movie is exist
        $checkAlreadyMovie = Movie::where('m_id', $id)->first();
        if (!is_null($checkAlreadyMovie)) {

            // Get Name and Vidoes, Recently watch of user
            $getMovieVideo = DB::select('
                                SELECT
                                movies.m_id AS id,
                                movies.m_name AS name,
                                movies.m_backdrop AS backdrop,
                                movies.m_type AS player,
                                movies.m_cloud AS cloud,
                                videos.v_id AS video_id,
                                videos.video_url AS video,
                                videos.duration,
                                videos.resolution AS resolution,
                                recently_watcheds.current_time,
                                recently_watcheds.duration_time
                                FROM movies
                                INNER JOIN videos ON videos.movie_id = movies.m_id
                                LEFT JOIN recently_watcheds  ON  recently_watcheds.movie_id = movies.m_id AND recently_watcheds.uid = "' . Auth::id() . '"
                                WHERE movies.m_id = "' . $id . '" AND videos.video_url IS NOT NULL
                                GROUP BY videos.v_id
                                ');

            // Get subtitle of movie
            $getMovieSubtitle = DB::table('subtitles')
                ->select('name AS subtitle_name', 'language AS subtitle_language')
                ->where('movie_id', $id)
                ->get();

            // Suggestion Next Movie
            $getSuggestionMovie = DB::table('movies')
                ->select('m_id AS id', 'm_name AS name', 'm_backdrop AS backdrop', 'm_type AS player', 'm_cloud AS cloud')
                ->where('m_id', '<>', $id)
                ->where('show', '<>', 0)
                ->orderBy(DB::raw('RAND()'))
                ->first();

            // Check if there is subtitle, if there not set null
            if ($getMovieSubtitle->isEmpty()) {
                $getMovieSubtitle = null;
            }


            $typeOfVideo = null;

            if ($checkAlreadyMovie->m_cloud == 'local') {

                // The type is video .mp4
                if ($getMovieVideo[0]->player === 'local' && substr($getMovieVideo[0]->video, strpos($getMovieVideo[0]->video, ".") + 1) === 'mp4') {

                    $typeOfVideo = 'mp4';

                } elseif ($getMovieVideo[0]->player === 'local' && substr($getMovieVideo[0]->video, strpos($getMovieVideo[0]->video, ".") + 1) === 'm3u8') {

                    // If HLS
                    $typeOfVideo = 'm3u8';

                } else {

                    $typeOfVideo = 'mp4';

                    $getMovieVideo[0]->video = $getMovieVideo[0]->video;
                }

                // check any response it will be
                if ($typeOfVideo === 'm3u8') {
                    return response()->json(
                        ['status' => 'success',
                            'data' => [
                                'video' => $getMovieVideo,
                                'subtitle' => $getMovieSubtitle,
                                'suggestion' => $getSuggestionMovie,
                                'video_format' => 'm3u8'
                            ]]
                    );
                } elseif ($typeOfVideo === 'mp4') {
                    return response()->json(
                        ['status' => 'success',
                            'data' => [
                                'video' => $getMovieVideo,
                                'subtitle' => $getMovieSubtitle,
                                'video_format' => 'mp4',
                                'suggestion' => $getSuggestionMovie
                            ]]
                    );
                }


            } elseif ($checkAlreadyMovie->m_cloud === 'aws') {

                // Add Cloudfront Link To Subtitle
                if ($getMovieSubtitle !== null) {
                    foreach ($getMovieSubtitle AS $key => $value) {
                        $getMovieSubtitle[$key]->subtitle_name = config('aws.cloudfront_public_url') . '/subtitles/' . $value->subtitle_name;
                    }
                }


                $typeOfVideo = null;
                $cookie_key = null;
                $cookie_policy = null;
                $cookie_signature = null;


                $cloudFront = new CloudFrontClient([
                    'region' => config('aws.private_bucket'),
                    'version' => '2014-11-06'
                ]);
                // Set up parameter values for the resource
                $resourceKey = config('aws.cloudfront_private_url') . '/videos/*';
                $resourceKey2 = config('aws.cloudfront_private_url') . '/videos/' . $getMovieVideo[0]->video;
                $expires = time() + (86400 * 30);

                $customPolicy = <<<POLICY
                            {
                                "Statement": [
                                    {
                                        "Resource": "{$resourceKey}",
                                        "Condition": {
                                            "DateLessThan": {"AWS:EpochTime": {$expires}}
                                        }
                                    }
                                ]
                                }
POLICY;

                // Create a signed URL for the resource using a custom policy
                $signedCookieCustomPolicy = $cloudFront->getSignedCookie([
                    'policy' => $customPolicy,
                    'private_key' => storage_path(config('aws.cloudfront_private_key_file')),
                    'key_pair_id' => config('aws.cloudfront_public_key')
                ]);


                $cfLink = '.' . substr(config('aws.cloudfront_private_url'), strpos(config('aws.cloudfront_private_url'), ".") + 1);

                foreach ($signedCookieCustomPolicy as $name => $value) {
                    if ($name === 'CloudFront-Key-Pair-Id') {
                        $cookie_key = cookie($name, $value, $expires, "", $cfLink, true, true);
                    } elseif ($name === 'CloudFront-Policy') {
                        $cookie_policy = cookie($name, $value, $expires, "", $cfLink, true, true);
                    } else {
                        $cookie_signature = cookie($name, $value, $expires, "", $cfLink, true, true);
                    }

                }


                // The type is video .mp4
                if ($getMovieVideo[0]->player === 'local' && substr($getMovieVideo[0]->video, strpos($getMovieVideo[0]->video, ".") + 1) === 'mp4') {


                    $getMovieVideo[0]->video = $resourceKey2;


                    $typeOfVideo = 'mp4';

                } elseif ($getMovieVideo[0]->player === 'local' && substr($getMovieVideo[0]->video, strpos($getMovieVideo[0]->video, ".") + 1) === 'm3u8') {

                    $getMovieVideo[0]->video = $resourceKey2;

                    // If HLS
                    $typeOfVideo = 'm3u8';

                } else {

                    $typeOfVideo = 'mp4';

                    $getMovieVideo[0]->video = $resourceKey2;
                }


                // check any response it will be
                if ($typeOfVideo === 'm3u8') {
                    return response()->json(
                        ['status' => 'success',
                            'data' => [
                                'video' => $getMovieVideo,
                                'subtitle' => $getMovieSubtitle,
                                'suggestion' => $getSuggestionMovie,
                                'video_format' => 'm3u8'
                            ]]
                    )->cookie($cookie_key)
                        ->cookie($cookie_policy)
                        ->cookie($cookie_signature);
                } elseif ($typeOfVideo === 'mp4') {
                    return response()->json(
                        ['status' => 'success',
                            'data' => [
                                'video' => $getMovieVideo,
                                'subtitle' => $getMovieSubtitle,
                                'video_format' => 'mp4',
                                'suggestion' => $getSuggestionMovie
                            ]]
                    )->cookie($cookie_key)
                        ->cookie($cookie_policy)
                        ->cookie($cookie_signature);
                }

            }


        } else {
            return response()->json(['status' => 404], 404);
        }
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @return void
     */
    public function getEpisodeVideo(Request $request)
    {
        $request->validate([
            'episode_id' => 'uuid',
            'series_id' => 'required|uuid',
            'type' => 'required|alpha|max:4',
        ]);

        // Check if the series is exist already
        $checkAlreadySeries = Series::find($request->input('series_id'));

        if (is_null($checkAlreadySeries)) {
            return response()->json(['status' => 404], 404);
        }

        // Get All season
        $getAllSeason = DB::select('
                                SELECT
                                episodes.id AS id,
                                episodes.name AS name,
                                episodes.backdrop AS backdrop,
                                episodes.series_id AS series_id,
                                episodes.episode_number AS episode_number,
                                episodes.season_number AS season_number,
                                episodes.cloud,
                                episodes.overview AS overview,
                                recently_watcheds.current_time,
                                recently_watcheds.duration_time
                                FROM episodes
                                LEFT JOIN recently_watcheds ON recently_watcheds.episode_id = episodes.id AND recently_watcheds.uid = "' . Auth::id() . '"
                                WHERE episodes.series_id = "' . $request->input('series_id') . '" 
                                ORDER BY season_number, episode_number + 0  ASC
                                ');

        $season = [];
        $next_season = null;
        // Check if there is no cast
        if (empty($getAllSeason)) {
            $season = null;
        } else {
            // Sort season and episode
            for ($i = 1; $i < count($getAllSeason); $i++) {
                for ($x = 0; $x < count($getAllSeason); $x++) {
                    if ($getAllSeason[$x]->season_number == $i) {
                        $season[$i][] = $getAllSeason[$x];
                    }
                }
            }
        }

        // if the episode choose from out list episode then is (cur)
        // if the episode choose from list episode then is (sp)
        if ($request->input('type') === 'cur') {

            // Get first episode id
            $getEpisodeNumber = DB::table('episodes')->select('id', 'season_number')->where('series_id', $request->input('series_id'))->orderBy('id', 'ASC')->first();

            // Get Name and Vidoes, Recently watch of user
            $getEpisodeVideo = DB::select('
                                SELECT
                                episodes.id AS id,
                                episodes.name AS name,
                                episodes.type,
                                episodes.backdrop AS backdrop,
                                episodes.series_id AS series_id,
                                episodes.episode_number AS episode_number,
                                episodes.season_number AS season_number,
                                episodes.cloud,
                                videos.v_id AS video_id,
                                videos.video_url AS video,
                                videos.resolution AS resolution,
                                videos.duration,
                                recently_watcheds.current_time,
                                recently_watcheds.duration_time
                                FROM episodes
                                INNER JOIN videos ON videos.episode_id = episodes.id
                                LEFT JOIN recently_watcheds  ON  recently_watcheds.episode_id =  episodes.id AND recently_watcheds.uid = "' . Auth::id() . '"
                                WHERE episodes.id = "' . $getEpisodeNumber->id . '" AND videos.video_url IS NOT NULL
                                GROUP BY episodes.id,videos.v_id');

            // Get subtitle of episode
            $getSeriesSubtitle = DB::table('subtitles')
                ->select('name AS subtitle_name', 'language AS subtitle_language')
                ->where('episode_id', $getEpisodeNumber->id)
                ->get();

            // Get next season
            $getNextSeason = DB::table('episodes')
                ->select('id', 'name', 'backdrop', 'overview', 'episode_number', 'season_number', 'series_id')
                ->where('season_number', $getEpisodeNumber->season_number + 1)
                ->groupBy('episode_number')
                ->orderBy('episode_number', 'ASC')
                ->first();

            // Check if there is subtitle, if there not set null
            if ($getSeriesSubtitle->isEmpty()) {
                $getSeriesSubtitle = null;
            }


            $typeOfVideo = null;
            $cookie_key = null;
            $cookie_policy = null;
            $cookie_signature = null;


            if ($getEpisodeVideo[0]->cloud === 'local') {


                // The type is video .mp4
                if ($getEpisodeVideo[0]->type === 'local' && substr($getEpisodeVideo[0]->video, strpos($getEpisodeVideo[0]->video, ".") + 1) === 'mp4') {

                    $typeOfVideo = 'mp4';

                } elseif ($getEpisodeVideo[0]->type === 'local' && substr($getEpisodeVideo[0]->video, strpos($getEpisodeVideo[0]->video, ".") + 1) === 'm3u8') {

                    // if HLS
                    $typeOfVideo = 'm3u8';

                } else {
                    $typeOfVideo = 'mp4';

                }


                if ($typeOfVideo === 'm3u8') {
                    return response()->json(
                        ['status' => 'success',
                            'data' => [
                                'episode' => $getEpisodeVideo,
                                'subtitle' => $getSeriesSubtitle,
                                'season' => $season,
                                'video_format' => 'm3u8',
                                'next_season' => $getNextSeason
                            ]]
                    );
                } elseif ($typeOfVideo === 'mp4') {
                    return response()->json(
                        ['status' => 'success',
                            'data' => [
                                'episode' => $getEpisodeVideo,
                                'subtitle' => $getSeriesSubtitle,
                                'season' => $season,
                                'video_format' => 'mp4',
                                'next_season' => $getNextSeason
                            ]]
                    );
                }


            }
            elseif ($getEpisodeVideo[0]->cloud === 'aws') {


                // Add Cloudfront Link To Subtitle
                if ($getSeriesSubtitle !== null) {
                    foreach ($getSeriesSubtitle AS $key => $value) {
                        $getSeriesSubtitle[$key]->subtitle_name = config('aws.cloudfront_public_url') . '/subtitles/' . $value->subtitle_name;
                    }
                }


                $cloudFront = new CloudFrontClient([
                    'region' => config('aws.private_bucket'),
                    'version' => '2014-11-06'
                ]);
                // Set up parameter values for the resource
                $resourceKey = config('aws.cloudfront_private_url') . '/videos/*';
                $resourceKey2 = config('aws.cloudfront_private_url') . '/videos/' . $getEpisodeVideo[0]->video;
                $expires = time() + (86400 * 30);

                $customPolicy = <<<POLICY
                            {
                                "Statement": [
                                    {
                                        "Resource": "{$resourceKey}",
                                        "Condition": {
                                            "DateLessThan": {"AWS:EpochTime": {$expires}}
                                        }
                                    }
                                ]
                                }
POLICY;

                // Create a signed URL for the resource using a custom policy
                $signedCookieCustomPolicy = $cloudFront->getSignedCookie([
                    'policy' => $customPolicy,
                    'private_key' => storage_path(config('aws.cloudfront_private_key_file')),
                    'key_pair_id' => config('aws.cloudfront_public_key')
                ]);


                $cfLink = '.' . substr(config('aws.cloudfront_private_url'), strpos(config('aws.cloudfront_private_url'), ".") + 1);

                foreach ($signedCookieCustomPolicy as $name => $value) {
                    if ($name === 'CloudFront-Key-Pair-Id') {
                        $cookie_key = cookie($name, $value, $expires, "", $cfLink, true, true);
                    } elseif ($name === 'CloudFront-Policy') {
                        $cookie_policy = cookie($name, $value, $expires, "", $cfLink, true, true);
                    } else {
                        $cookie_signature = cookie($name, $value, $expires, "", $cfLink, true, true);
                    }

                }


                // The type is video .mp4
                if ($getEpisodeVideo[0]->type === 'local' && substr($getEpisodeVideo[0]->video, strpos($getEpisodeVideo[0]->video, ".") + 1) === 'mp4') {

                    $typeOfVideo = 'mp4';

                    $getEpisodeVideo[0]->video = $resourceKey2;

                } elseif ($getEpisodeVideo[0]->type === 'local' && substr($getEpisodeVideo[0]->video, strpos($getEpisodeVideo[0]->video, ".") + 1) === 'm3u8') {

                    // if HLS
                    $typeOfVideo = 'm3u8';

                    $getEpisodeVideo[0]->video = $resourceKey2;

                } else {
                    $typeOfVideo = 'mp4';

                    $getEpisodeVideo[0]->video = $resourceKey2;
                }


                // check any response it will be
                if ($typeOfVideo === 'm3u8') {
                    return response()->json(
                        ['status' => 'success',
                            'data' => [
                                'episode' => $getEpisodeVideo,
                                'subtitle' => $getSeriesSubtitle,
                                'season' => $season,
                                'video_format' => 'm3u8',
                                'next_season' => $getNextSeason
                            ]]
                    )->cookie($cookie_key)
                        ->cookie($cookie_policy)
                        ->cookie($cookie_signature);
                } elseif ($typeOfVideo === 'mp4') {
                    return response()->json(
                        ['status' => 'success',
                            'data' => [
                                'episode' => $getEpisodeVideo,
                                'subtitle' => $getSeriesSubtitle,
                                'season' => $season,
                                'video_format' => 'mp4',
                                'next_season' => $getNextSeason
                            ]]
                    )->cookie($cookie_key)
                        ->cookie($cookie_policy)
                        ->cookie($cookie_signature);
                }


            }


        }
        elseif ($request->input('type') === 'sp') {

            // Get first episode id
            $getEpisodeNumber = DB::table('episodes')->select('id', 'season_number')->where('series_id', $request->input('series_id'))->orderBy('id', 'ASC')->first();

            // Get Name and Vidoes, Recently watch of user
            $getEpisodeVideo = DB::select('
                                SELECT
                                episodes.id AS id,
                                episodes.type,
                                episodes.name AS name,
                                episodes.backdrop AS backdrop,
                                episodes.series_id AS series_id,
                                episodes.episode_number AS episode_number,
                                episodes.season_number AS season_number,
                                episodes.cloud,
                                videos.v_id AS video_id,
                                videos.video_url AS video,
                                videos.resolution AS resolution,
                                videos.duration,
                                recently_watcheds.current_time,
                                recently_watcheds.duration_time
                                FROM episodes
                                LEFT JOIN recently_watcheds ON recently_watcheds.episode_id = episodes.id AND recently_watcheds.uid = "' . Auth::id() . '"
                                JOIN videos ON videos.episode_id = episodes.id
                                WHERE episodes.id = "' . $request->input('episode_id') . '" AND episodes.series_id = "' . $request->input('series_id') . '" AND videos.video_url IS NOT NULL
                                GROUP BY episodes.id,videos.v_id');

            // Get subtitle of episode
            $getSeriesSubtitle = DB::table('subtitles')
                ->select('name AS subtitle_name', 'language AS subtitle_language')
                ->where('episode_id', $request->input('episode_id'))
                ->get();

            // Get Season Epiosde
            $getNextSeason = DB::table('episodes')
                ->select('id', 'name', 'backdrop', 'overview', 'episode_number', 'season_number', 'series_id')
                ->where('season_number', $getEpisodeNumber->season_number + 1)
                ->groupBy('episode_number')
                ->orderBy('episode_number', 'ASC')
                ->first();

            // Check if there is subtitle, if there not set null
            if ($getSeriesSubtitle->isEmpty()) {
                $getSeriesSubtitle = null;
            }


            $typeOfVideo = null;
            $cookie_key = null;
            $cookie_policy = null;
            $cookie_signature = null;


            if ($getEpisodeVideo[0]->cloud === 'local') {


                // The type is video .mp4
                if ($getEpisodeVideo[0]->type === 'local' && substr($getEpisodeVideo[0]->video, strpos($getEpisodeVideo[0]->video, ".") + 1) === 'mp4') {

                    $typeOfVideo = 'mp4';

                } elseif ($getEpisodeVideo[0]->type === 'local' && substr($getEpisodeVideo[0]->video, strpos($getEpisodeVideo[0]->video, ".") + 1) === 'm3u8') {

                    $typeOfVideo = 'm3u8';

                } else {
                    $typeOfVideo = 'mp4';
                }


                if ($typeOfVideo === 'm3u8') {
                    return response()->json(
                        ['status' => 'success',
                            'data' => [
                                'episode' => $getEpisodeVideo,
                                'subtitle' => $getSeriesSubtitle,
                                'season' => $season,
                                'video_format' => 'm3u8',
                                'next_season' => $getNextSeason
                            ]]
                    );
                } elseif ($typeOfVideo === 'mp4') {
                    return response()->json(
                        ['status' => 'success',
                            'data' => [
                                'episode' => $getEpisodeVideo,
                                'subtitle' => $getSeriesSubtitle,
                                'season' => $season,
                                'video_format' => 'mp4',
                                'next_season' => $getNextSeason
                            ]]
                    );
                }


            } elseif ($getEpisodeVideo[0]->cloud === 'aws') {

                // Add Cloudfront Link To Subtitle
                if ($getSeriesSubtitle !== null) {
                    foreach ($getSeriesSubtitle AS $key => $value) {
                        $getSeriesSubtitle[$key]->subtitle_name = config('aws.cloudfront_public_url') . '/subtitles/' . $value->subtitle_name;
                    }
                }

                $cloudFront = new CloudFrontClient([
                    'region' => config('aws.private_bucket'),
                    'version' => '2014-11-06'
                ]);
                // Set up parameter values for the resource
                $resourceKey = config('aws.cloudfront_private_url') . '/videos/' . $checkAlreadySeries->t_name . '/*';
                $resourceKey2 = config('aws.cloudfront_private_url') . '/videos/' . $getEpisodeVideo[0]->video;
                $expires = time() + (86400 * 30);

                $customPolicy = <<<POLICY
                            {
                                "Statement": [
                                    {
                                        "Resource": "{$resourceKey}",
                                        "Condition": {
                                            "DateLessThan": {"AWS:EpochTime": {$expires}}
                                        }
                                    }
                                ]
                                }
POLICY;

                // Create a signed URL for the resource using a custom policy
                $signedCookieCustomPolicy = $cloudFront->getSignedCookie([
                    'policy' => $customPolicy,
                    'private_key' => storage_path(config('aws.cloudfront_private_key_file')),
                    'key_pair_id' => config('aws.cloudfront_public_key')
                ]);


                $cfLink = '.' . substr(config('aws.cloudfront_private_url'), strpos(config('aws.cloudfront_private_url'), ".") + 1);

                foreach ($signedCookieCustomPolicy as $name => $value) {
                    if ($name === 'CloudFront-Key-Pair-Id') {
                        $cookie_key = cookie($name, $value, $expires, "", $cfLink, true, true);
                    } elseif ($name === 'CloudFront-Policy') {
                        $cookie_policy = cookie($name, $value, $expires, "", $cfLink, true, true);
                    } else {
                        $cookie_signature = cookie($name, $value, $expires, "", $cfLink, true, true);
                    }

                }


                // The type is video .mp4
                if ($getEpisodeVideo[0]->type === 'local' && substr($getEpisodeVideo[0]->video, strpos($getEpisodeVideo[0]->video, ".") + 1) === 'mp4') {

                    $typeOfVideo = 'mp4';

                    $getEpisodeVideo[0]->video = $resourceKey2;

                } elseif ($getEpisodeVideo[0]->type === 'local' && substr($getEpisodeVideo[0]->video, strpos($getEpisodeVideo[0]->video, ".") + 1) === 'm3u8') {

                    // if HLS
                    $typeOfVideo = 'm3u8';

                    $getEpisodeVideo[0]->video = $resourceKey2;

                } else {
                    $typeOfVideo = 'mp4';

                    $getEpisodeVideo[0]->video = $resourceKey2;
                }


                // check any response it will be
                if ($typeOfVideo === 'm3u8') {
                    return response()->json(
                        ['status' => 'success',
                            'data' => [
                                'episode' => $getEpisodeVideo,
                                'subtitle' => $getSeriesSubtitle,
                                'season' => $season,
                                'video_format' => 'm3u8',
                                'next_season' => $getNextSeason
                            ]]
                    )->cookie($cookie_key)
                        ->cookie($cookie_policy)
                        ->cookie($cookie_signature);
                } elseif ($typeOfVideo === 'mp4') {
                    return response()->json(
                        ['status' => 'success',
                            'data' => [
                                'episode' => $getEpisodeVideo,
                                'subtitle' => $getSeriesSubtitle,
                                'season' => $season,
                                'video_format' => 'mp4',
                                'next_season' => $getNextSeason
                            ]]
                    )->cookie($cookie_key)
                        ->cookie($cookie_policy)
                        ->cookie($cookie_signature);
                }


            }
        }/**/
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @return void
     */
    public function movieReport(Request $request)
    {
        $request->validate([
            'type' => 'required|numeric',
            'details' => 'nullable|max:100',
            'id' => 'required|uuid',
        ]);

        $add = new Report();
        $add->report_type = $request->type;
        $add->report_details = $request->details;
        $add->report_userid = Auth::user()->id;
        $add->report_movie = $request->id;
        $add->save();

        return response()->json(['status' => 'success']);
    }

    public function seriesReport(Request $request)
    {
        $request->validate([
            'type' => 'required|numeric',
            'details' => 'nullable|max:100',
            'episode_id' => 'required|uuid',
            'series_id' => 'required|uuid',
        ]);

        $add = new Report();
        $add->report_type = $request->type;
        $add->report_details = $request->details;
        $add->report_userid = Auth::user()->id;
        $add->report_episode = $request->episode_id;
        $add->report_series = $request->series_id;
        $add->save();

        return response()->json(['status' => 'success']);
    }

    /**
     * Store recently time episode
     *
     * @return \Illuminate\Http\Response
     */
    public function setRecentlyTimeEpiosde(Request $request)
    {
        $request->validate([
            'current_time' => 'required|numeric',
            'duration_time' => 'required|numeric',
            'episode_id' => 'required|uuid',
            'series_id' => 'required|uuid',
        ]);

        $recently = Recently_watched::join('episodes', 'episodes.id', '=', 'recently_watcheds.episode_id')
            ->where('recently_watcheds.uid', Auth::id())
            ->where('episodes.id', '=', $request->episode_id)
            ->where('episodes.series_id', '=', $request->series_id)
            ->orderBy('episodes.episode_number', 'DESC')
            ->first();

        if (!is_null($recently)) {
            $recently->episode_id = $request->episode_id;
            $recently->current_time = $request->current_time;
            $recently->duration_time = $request->duration_time;
            $recently->save();
            return response()->json(['status' => 'success']);
        } else {
            $store = new Recently_watched();
            $store->episode_id = $request->episode_id;
            $store->series_id = $request->series_id;
            $store->uid = Auth::id();
            $store->current_time = $request->current_time;
            $store->duration_time = $request->duration_time;
            $store->save();
            return response()->json(['status' => 'success']);
        }
        return response()->json(['status' => 'failed'], 404);
    }

    /**
     * Store recently time episode
     *
     * @return \Illuminate\Http\Response
     */
    public function setRecentlyTimeMovie(Request $request)
    {
        $request->validate([
            'current_time' => 'required|numeric',
            'duration_time' => 'required|numeric',
            'movie_id' => 'required|uuid',
        ]);

        $recently = Recently_watched::where('uid', Auth::id())->where('movie_id', $request->movie_id)->first();

        if (!is_null($recently)) {
            $recently->current_time = $request->current_time;
            $recently->save();
            return response()->json(['status' => 'success']);
        } else {
            $store = new Recently_watched();
            $store->movie_id = $request->movie_id;
            $store->uid = Auth::id();
            $store->current_time = $request->current_time;
            $store->duration_time = $request->duration_time;
            $store->save();
            return response()->json(['status' => 'success']);
        }
        return response()->json(['status' => 'failed'], 404);
    }
}
