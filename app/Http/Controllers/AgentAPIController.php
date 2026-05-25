<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Collection;
use App\Models\SuspiciousActivity;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AgentAPIController extends Controller
{
    /**
     * Fetch businesses in the agent's assigned zone
     */
    public function getAssignedBusinesses()
    {
        $user = Auth::user();
        if (!$user->zone_id) {
            return response()->json(['error' => 'No zone assigned'], 403);
        }

        $businesses = Business::where('zone_id', $user->zone_id)->get();
        return response()->json($businesses);
    }

    /**
     * Record a collection (Sync point for PWA)
     */
    public function storeCollection(Request $request)
    {
        $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'amount' => 'required|numeric',
            'gps_lat' => 'required',
            'gps_lng' => 'required',
            'collected_at' => 'required',
        ]);

        $business = Business::find($request->business_id);
        
        // Geo-fencing Check
        $distance = $this->calculateDistance(
            $request->gps_lat, $request->gps_lng, 
            $business->gps_lat, $business->gps_lng
        );

        if ($distance > 0.5) { // 500 meters threshold
            SuspiciousActivity::log(
                'GPS_MISMATCH', 
                Auth::id(), 
                "Agent collected from {$business->name} at a distance of {$distance}km"
            );
        }

        $collection = Collection::create([
            'business_id' => $request->business_id,
            'agent_id' => Auth::id(),
            'amount' => $request->amount,
            'gps_lat' => $request->gps_lat,
            'gps_lng' => $request->gps_lng,
            'collected_at' => $request->collected_at,
            'offline_sync_id' => $request->offline_sync_id
        ]);

        return response()->json([
            'message' => 'Collection synced successfully',
            'receipt_number' => $collection->receipt_number
        ]);
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        return $miles * 1.609344; // to KM
    }
}
