<?php

namespace App\Http\Controllers\Pets;

use App\Models\Pets\Pet;
use App\Models\Pets\Owner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\Pets\PetResource;
use App\Http\Resources\Pets\PetCollection;

class PetsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        Gate::authorize("viewAny",Pet::class);
        $search = $request->get("search");
        $species = $request->get("species");
        // where(DB::raw("pets.name"),"ilike","%".$search."%")
        //         ->
        $pets = Pet::where(function($q) use($search,$species){
                    if($species){
                        $q->where("specie",$species);
                    }
                    if($search){
                        $q->whereHas("owner",function($q) use($search){
                            $q->where(DB::raw("pets.name || ' ' || owners.first_name || ' ' || COALESCE(owners.last_name,'') || ' ' || owners.phone || ' ' || owners.n_document"),"ilike","%".$search."%");
                        });
                    }
                })
                ->orderBy("id","desc")->paginate(4);

        return response()->json([
            "total_page" => $pets->lastPage(),
            "pets" => PetCollection::make($pets),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        Gate::authorize("create",Pet::class);
        if($request->hasFile("imagen")){
            $path = Storage::putFile("pets",$request->file("imagen"));
            $request->request->add(["photo" => $path]);
        }
        if($request->dirth_date){
            $request->request->add(["dirth_date" => $request->dirth_date." 00:00:00"]);
        }
        $owner = Owner::create([
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
            'email'  => $request->email,
            'phone'  => $request->phone,
            'address'  => $request->address,
            'city'  => $request->city,
            'emergency_contact'  => $request->emergency_contact,
            'type_document'  => $request->type_document,
            'n_document'  => $request->n_document,
        ]);
        $request->request->add([
            "owner_id" => $owner->id
         ]);
        $pet = Pet::create($request->all());
        // $pet->update([
        //     "owner_id" => $owner->id
        // ]);
        return response()->json([
            "message" => 200,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        Gate::authorize("view",Pet::class);
        $pet = Pet::findOrFail($id);
        return response()->json([
            "pet" => PetResource::make($pet),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        Gate::authorize("update",Pet::class);
        $pet = Pet::findOrFail($id);
        if($request->hasFile("imagen")){
            if($pet->avatar){
                Storage::delete($pet->avatar);
            }
            $path = Storage::putFile("pets",$request->file("imagen"));
            $request->request->add(["photo" => $path]);
        }
        if($request->dirth_date){
            $request->request->add(["dirth_date" => $request->dirth_date." 00:00:00"]);
        }
        $pet->update($request->all());

        $owner = $pet->owner;
        $owner->update([
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
            'email'  => $request->email,
            'phone'  => $request->phone,
            'address'  => $request->address,
            'city'  => $request->city,
            'emergency_contact'  => $request->emergency_contact,
            'type_document'  => $request->type_document,
            'n_document'  => $request->n_document,
        ]);
        
        return response()->json([
            "message" => 200,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        Gate::authorize("delete",Pet::class);
        $pet = Pet::findOrFail($id);
        if($pet->photo){
            Storage::delete($pet->photo);
        }
        $pet->delete();

        return response()->json([
            "message" => 200,
        ]);
    }
}
