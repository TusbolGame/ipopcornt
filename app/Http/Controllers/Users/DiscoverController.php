<?php

namespace App\Http\Controllers\Users;

use App\Appearance;
use App\Helpers;
use App\Movie;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class DiscoverController extends Controller
{

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            Helpers::checkUserPayment(Auth::user());

            return $next($request);
        });

    }


    /**
     * This mix of series and movies
     *
     * @return \Illuminate\Http\Response
     */
    public function getHomeResult()
    {


        // This array to for loop to determine the type and genre
        $genres = [
            [
                'genre' => 'Action',
                'type' => 'movies',
            ],
            [
                'genre' => 'Drama',
                'type' => 'series',
            ],
            [
                'genre' => 'Action & Adventure',
                'type' => 'series',
            ],
            [
                'genre' => 'Crime',
                'type' => 'movies',
            ],
            [
                'genre' => 'Fantasy',
                'type' => 'movies',
            ],
            [
                'genre' => 'Drama',
                'type' => 'movies',
            ],
            [
                'genre' => 'Crime',
                'type' => 'series',
            ],
            [
                'genre' => 'Family',
                'type' => 'series',
            ],
            [
                'genre' => 'Action & Adventure',
                'type' => 'movies',
            ],
            [
                'genre' => 'Horror',
                'type' => 'movies',
            ],
            [
                'genre' => 'Mystery',
                'type' => 'series',
            ],
            [
                'genre' => 'Romance',
                'type' => 'movies',
            ],
            [
                'genre' => 'Sci-Fi & Fantasy',
                'type' => 'series',
            ],
            [
                'genre' => 'Thriller',
                'type' => 'movies',
            ],
            [
                'genre' => 'MystWar & Politicsery',
                'type' => 'series',

            ],
            [
                'genre' => 'Sci-Fi & Fantasy',
                'type' => 'movies',
            ],
            [
                'genre' => 'Documentary',
                'type' => 'movies',
            ],
            [
                'genre' => 'Comedy',
                'type' => 'movies',
            ],
            [
                'genre' => 'Comedy',
                'type' => 'series',
            ],
            [
                'genre' => 'War',
                'type' => 'movies',
            ],
            [
                'genre' => 'War',
                'type' => 'series',
            ],
        ];

        // Execute query and push it in array
        $getMasByGenre = [];
        for ($a = 0; $a < count($genres); $a++) {
            if ($genres[$a]['type'] === 'movies') {
                $movieQuery = DB::select('
                      SELECT
                      "movie" AS type,
                      movies.m_id AS id,
                      movies.m_name AS name,
                      movies.m_poster AS poster,
                      movies.m_desc AS overview,
                      movies.m_runtime AS runtime,
                      movies.m_year AS year,
                      movies.m_genre AS genre,
                      movies.m_rate AS rate,
                      movies.m_backdrop AS backdrop,
                      movies.m_age AS age,
                      movies.m_type AS player,
                      u2.current_time,
                      u2.duration_time,
                      CASE
                      WHEN u1.id IS NULL THEN false
                      ELSE true
                      END AS "is_favorite",
                      CASE
                      WHEN u3.id IS NULL THEN false
                      ELSE true
                      END AS "is_like",
                      movies.m_cloud AS cloud
                      FROM movies
                      LEFT JOIN collection_lists AS u1  ON u1.movie_id = movies.m_id AND u1.uid = "' . Auth::id() . '"
                      LEFT JOIN recently_watcheds AS u2 ON u2.movie_id = movies.m_id AND u2.uid = "' . Auth::id() . '"
                      LEFT JOIN likes AS u3  ON u3.movie_id = movies.m_id AND u3.uid = "' . Auth::id() . '"
                      WHERE movies.m_age <> "G"  AND  movies.m_genre LIKE "' . $genres[$a]['genre'] . '%" AND movies.show <> 0
                      GROUP BY movies.m_id DESC 
                      LIMIT 10');
                array_push($getMasByGenre, [
                    'list' => $movieQuery,
                    'genre' => $genres[$a]['genre'],
                    'type' =>  'Movies'
                ]);
            } elseif ($genres[$a]['type'] === 'series') {

                // if type eqaule series get the series by genre
                $seriesQuery = DB::select('
                                SELECT
                                "series" AS type,
                                series.t_id AS id,
                                series.t_name AS name,
                                series.t_desc AS overview,
                                series.t_backdrop AS backdrop,
                                series.t_genre AS genre,
                                series.t_year AS year,
                                series.t_rate AS rate,
                                series.t_poster AS poster,
                                series.t_age AS age,
                                u2.current_time,
                                u2.duration_time,
                                CASE
                                WHEN u1.id IS NULL THEN false
                                ELSE true
                                END AS "is_favorite",
                                CASE
                                WHEN u3.id IS NULL THEN false
                                ELSE true
                                END AS "is_like",
                                CASE
                                WHEN u4.series_id IS NULL OR u4.show = 0 THEN false
                                ELSE true
                                END AS "already_episode",
                                CASE
                                WHEN u4.type = "local" OR u4.type = "link" THEN "default"
                                ELSE "embed"
                                END AS "player",
                                series.t_cloud AS cloud
                                FROM series
                                LEFT JOIN collection_lists AS u1  ON u1.series_id = series.t_id AND u1.uid = "' . Auth::id() . '"
                                LEFT JOIN recently_watcheds AS u2 ON u2.series_id = series.t_id AND u2.uid = "' . Auth::id() . '"
                                LEFT JOIN likes    AS u3  ON u3.series_id = series.t_id AND u3.uid = "' . Auth::id() . '"
                                LEFT JOIN episodes AS u4  ON u4.series_id = series.t_id
                                WHERE series.t_age <> "G"  AND  series.t_genre LIKE  "' . $genres[$a]['genre'] . '%"
                                GROUP BY series.t_id DESC
                                LIMIT 10');

                array_push($getMasByGenre, [
                    'list' => $seriesQuery,
                    'genre' => $genres[$a]['genre'],
                    'type' => 'Series',
                ]);
            }
        }


        // Get top movies and series
        $getTopMas = DB::select('(SELECT
                                "movie" AS type,
                                movies.m_id AS id,
                                movies.m_name AS name,
                                movies.m_poster AS poster,
                                movies.m_desc AS overview,
                                movies.m_year AS year,
                                movies.m_genre AS genre,
                                movies.m_rate AS rate,
                                movies.m_backdrop AS backdrop,
                                movies.m_poster AS poster,
                                movies.m_age AS age,
                                u2.current_time,
                                u2.duration_time,
                                CASE
                                WHEN u1.id IS NULL THEN false
                                ELSE true
                                END AS "is_favorite",
                                movies.m_type AS player,
                                movies.m_cloud AS cloud
                                FROM tops
                                INNER JOIN movies  ON movies.m_id = tops.movie_id
                                LEFT JOIN collection_lists AS u1  ON u1.movie_id = movies.m_id AND u1.uid = "' . Auth::id() . '"
                                LEFT JOIN recently_watcheds AS u2 ON u2.movie_id = movies.m_id AND u2.uid = "' . Auth::id() . '"
                                GROUP BY movies.m_id DESC)
                                UNION
                                (SELECT
                                "series" AS type,
                                series.t_id AS id,
                                series.t_name AS name,
                                series.t_poster AS poster,
                                series.t_desc AS overview,
                                series.t_year AS year,
                                series.t_genre AS genre,
                                series.t_rate AS rate,
                                series.t_backdrop AS backdrop,
                                series.t_poster AS poster,
                                series.t_age AS age,
                                u2.current_time,
                                u2.duration_time,
                                CASE
                                WHEN u1.id IS NULL THEN false
                                ELSE true
                                END AS "is_favorite",
                                CASE
                                WHEN u4.type = "local" OR u4.type = "link" THEN "default"
                                ELSE "embed"
                                END AS "player",
                                series.t_cloud AS cloud
                                FROM tops
                        	    INNER JOIN series  ON series.t_id = tops.series_id
                                LEFT JOIN collection_lists AS u1  ON u1.series_id = series.t_id AND u1.uid = "' . Auth::id() . '"
                                LEFT JOIN recently_watcheds AS u2 ON u2.series_id = series.t_id AND u2.uid = "' . Auth::id() . '"
                                LEFT JOIN episodes AS u4  ON u4.series_id = series.t_id
                                GROUP BY series.t_id DESC)');

        if (empty($getTopMas)) {
            $getTopMas = null;
        }

        // Last 3 Recently Watched
        $getRecentlyQuery = DB::select('
                      (SELECT
                      "movie" AS type,
                      movies.m_id AS id,
                      movies.m_name AS name,
                      movies.m_backdrop AS backdrop,
                      recently_watcheds.current_time,
                      recently_watcheds.duration_time,
                      movies.m_type AS player,
                      movies.m_cloud AS cloud
                      FROM recently_watcheds
                      JOIN movies ON movies.m_id = recently_watcheds.movie_id
                      WHERE recently_watcheds.uid = "' . Auth::id() . '"
                      )
                      UNION
                      (SELECT
                      "series" AS type,
                      episodes.series_id,
                      episodes.name,
                      episodes.backdrop,
                      recently_watcheds.current_time,
                      recently_watcheds.duration_time,
                      episodes.type AS player,
                      episodes.cloud 
                      FROM recently_watcheds
                      JOIN episodes ON episodes.id = recently_watcheds.episode_id
                      WHERE recently_watcheds.uid = "' . Auth::id() . '"
                      ) LIMIT 3');

        if (empty($getRecentlyQuery)) {
            $getRecentlyQuery = null;
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'data' => $getMasByGenre,
                'top' => $getTopMas,
                'recenlty' => $getRecentlyQuery
            ]], 200);
    }

    /**
     * Get Notifcation
     *
     * @return void
     */
    public function getNotifaction() {
        
        // Get support

        $getSupport= DB::table('supports')
                        ->selectRaw('supports.*, support_responses.readit, support_responses.reply')
                        ->leftJoin('support_responses', function($join){
                            $join->on('support_responses.request_id', '=', 'supports.request_id')
                            ->where( 'support_responses.from', '=', 'support');
                        })
                        ->where('supports.uid', Auth::id())
                        ->where('support_responses.readit', 1)
                        ->groupBy('supports.id')
                        ->get();

        if ($getSupport->isEmpty()) {
            $getSupport = null;
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'support' => $getSupport
            ]], 200);
    }


}
