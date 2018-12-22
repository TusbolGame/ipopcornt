<?php

namespace App\Http\Controllers\Ghost;

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
            $getChannels = null;
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
}
