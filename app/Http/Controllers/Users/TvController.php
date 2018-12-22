<?php

namespace App\Http\Controllers\Users;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Helpers;
use App\Tv;
use DB;
use App;
use Auth;
use Cookie;

class TvController extends Controller
{
    private $dataLink = null;

    /**
     * This Constructer check if the user is make payment or not if not it will return 404
     *
     * TvController constructor.
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            Helpers::checkUserPayment(Auth::user());

            return $next($request);
        });
    }


    /**
     * Get all channels
     *
     * @return []
     */
    public function getAll()
    {
        $getChannels = Tv::first();

        $getChannelsQueryByGenre = [];

        // Check if there is no channel
        if (is_null($getChannels)) {
            $getChannelsQueryByGenre = null;
        } else {

        // This array to for loop to determine the type and genre
            $genres = [ 'Adult',
                    'Animated',
                    'Cooking',
                    'DIY',
                    'Documentary',
                    'Dramality',
                    'Educational',
                    'Factual',
                    'Instructional',
                    'Lifestyle',
                    'Movies',
                    'Music',
                    'News',
                    'Others',
                    'Reality',
                    'Religious',
                    'Science',
                    'Shopping',
                    'Sports',
                    'Childrens'];


            // Execute query and push it in array
            for ($a = 0; $a < count($genres); $a++) {
                $channelsQuery = DB::select('
                      SELECT
                      *
                      FROM tvs
                      WHERE genre LIKE "' . $genres[$a] . '%"  AND streaming_status <> 0
                      LIMIT 100');

                array_push($getChannelsQueryByGenre, [
                    'list' => $channelsQuery,
                    'genre' => $genres[$a],
                    'type' => 'channels',
                ]);    
            }


        }
        return response()->json(
            ['data' => [
                'channels' => $getChannelsQueryByGenre
            ]]

        );
    }

    /**
     * Get some channel info
     *
     * @param [uuid] $id
     * @return []
     */
    public function getChannelDetails($id)
    {
        $check = Tv::find($id);

        if (is_null($check)) {
            return response()->json(['status' => 404], 404);
        }


        $getM3u8 = DB::table('tvs')->where('id', $id)->get();
        
        return response()->json(
            ['status' => 'success',
                'data' => [
                    'video' => $getM3u8,
                    'channel_list' => DB::table('tvs')->limit(10)->get()
                ]]
        );
    }


    public function searchChannel(Request $request)
    {
        $request->validate([
            'query' => 'required|string|max:15',
        ]);

        
        $getChannels = DB::select('
                    SELECT
                    *
                    FROM tvs
                    WHERE tvs.name  LIKE "'. $request->input('query') .'%"
            ');

        // Get comain
        return response()->json(
            ['status' => 'success',
                'data' => [
                    'channel_list' => $getChannels
                ]]
        );
    }
}
