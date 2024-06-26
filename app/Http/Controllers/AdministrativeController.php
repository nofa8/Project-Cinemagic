<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\AdministrativeFormRequest;
use App\Policies\AdministrativePolicy;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class AdministrativeController extends \Illuminate\Routing\Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        //$this->authorizeResource(User::class, 'administrative');
    }

    public function index(Request $request): View
    {
        $currentAdminId = auth()->id();
        $administrativesQuery = User::where('type', 'A')->orWhere('type', 'E')->where('id','!=',$currentAdminId)
            ->orderBy('name');

        $filterByName = $request->query('name');
        if ($filterByName) {
            $administrativesQuery->where('name', 'like', "%$filterByName%");
        }
        $administratives = $administrativesQuery
            ->paginate(20)
            ->withQueryString();

        return view(
            'administratives.index',
            compact('administratives', 'filterByName')
        );
    }


    public function indexDeleted(Request $request): View
    {

        $administrativesQuery = User::onlyTrashed()
                    ->where('type','!=', 'C')
                    ->orderBy('name');       
        $filterByName = $request->query('name');
        if ($filterByName) {
            $administrativesQuery->where('name', 'like', "%$filterByName%");
        }
        $administratives = $administrativesQuery
            ->paginate(20)
            ->withQueryString();
        
        return view(
            'administratives.index',
            compact('administratives', 'filterByName')
        )->with('tr',"trash");
    }

    public function save(User $user): RedirectResponse{
        if (!$user->trashed()){
            return redirect()->back()
                ->with('alert-type', 'error')
                ->with('alert-msg', "User \"{$user->name}\" is not in the deleted list.");;    
        }
        if ($user?->customerD?->trashed()){
            $user->customerD->restore();
        }
        $user->restore();
        return redirect()->back()->with('alert-type', 'success')
        ->with('alert-msg', "User \"{$user->name}\" has been restored.");;
    }
 
    public function destruction (User $user): RedirectResponse{
        if (!$user->trashed()){
            return redirect()->route('administratives.deleted')
                ->with('alert-type', 'error')
                ->with('alert-msg', "User \"{$user->name}\" is not in the deleted list.");
        }
        if ($user->photo_filename && Storage::exists('public/photos/' . $user->photo_filename)) {
            Storage::delete('public/photos/' . $user->photo_filename);
        }
        if (!empty($user?->customerD)){
            $user->customerD->forceDelete();
        }
        $name = $user->name;
        $user->forceDelete();
        return redirect()->back()
            ->with('alert-type', 'success')
            ->with('alert-msg', "User \"{$name}\" has been permanently deleted.");
    }

    public function show(User $administrative): View
    {
        return view('administratives.show')->with('administrative', $administrative);
    }

    public function create(): View
    {
        $newAdministrative = new User();
        $newAdministrative->type = 'A';
        return view('administratives.create')
            ->with('administrative', $newAdministrative);
    }

    public function store(AdministrativeFormRequest $request): RedirectResponse
    {
        $validatedData = $request->validated();
        $newAdministrative = new User();

        $newAdministrative->name = $validatedData['name'];
        $newAdministrative->email = $validatedData['email'];
        // Only sets admin field if it has permission  to do it.
        // Otherwise, admin is false

        $newAdministrative->type = $validatedData['type'];

        // Initial password is always 123
        $newAdministrative->password = bcrypt('123');
        $newAdministrative->save();

        if ($request->hasFile('photo_filename')) {
            $path = $request->photo_file->store('public/photos');
            $newAdministrative->photo_filename = basename($path);
            $newAdministrative->save();
        }
        $newAdministrative->sendEmailVerificationNotification();
        $url = route('administratives.show', ['administrative' => $newAdministrative]);
        $htmlMessage = "Administrative <a href='$url'><u>{$newAdministrative->name}</u></a> has been created successfully!";
        return redirect()->route('administratives.index')
            ->with('alert-type', 'success')
            ->with('alert-msg', $htmlMessage);
    }


    public function edit(User $administrative): View
    {
        return view('administratives.edit')
            ->with('administrative', $administrative);
    }

    public function update(AdministrativeFormRequest $request, User $administrative): RedirectResponse
    {
        $validatedData = $request->validated();

        $administrative->name = $validatedData['name'];
        $administrative->email = $validatedData['email'];
        // Only updates admin field if it has permission  to do it.
        // Otherwise, do not change it (ignore it)

        $administrative->type = $validatedData['type'];
        

        $administrative->save();
        if ($request->hasFile('photo_file')) {
            // Delete previous file (if any)
            if (
                $administrative->photo_filename &&
                Storage::fileExists('public/photos/' . $administrative->photo_filename)
            ) {
                Storage::delete('public/photos/' . $administrative->photo_filename);
            }
            $path = $request->photo_file->store('public/photos');
            $administrative->photo_filename = basename($path);
            $administrative->save();
        }
        $url = route('administratives.show', ['administrative' => $administrative]);
        $htmlMessage = "Administrative <a href='$url'><u>{$administrative->name}</u></a> has been updated successfully!";
        return redirect()->route('administratives.index')
            ->with('alert-type', 'success')
            ->with('alert-msg', $htmlMessage);

        if ($request->user()->can('viewAny', User::class)) {
            return redirect()->route('administrative.index')
            ->with('alert-type', 'success')
            ->with('alert-msg', $htmlMessage);
        }
        return redirect()->back()
            ->with('alert-type', 'success')
            ->with('alert-msg', $htmlMessage);

    }

    public function destroy(User $administrative): RedirectResponse
    {
        try {
            $url = route('administratives.show', ['administrative' => $administrative]);
            $fileToDelete = $administrative->photo_filename;

            if ($administrative->customer()->count() != 0){
                $administrative->customer()->purchases()->each(function ($purchase) {
                    $purchase->customer_id=null;
                    $purchase->save();
                });
                $administrative->customer->delete();
            }

            $administrative->delete();
            if ($fileToDelete) {
                if (Storage::fileExists('public/photos/' . $fileToDelete)) {
                    Storage::delete('public/photos/' . $fileToDelete);
                }
            }
            $alertType = 'success';
            $alertMsg = "Administrative {$administrative->name} has been deleted successfully!";
        } catch (\Exception $error) {
            $alertType = 'danger';
            $alertMsg = "It was not possible to delete the administrative
                            <a href='$url'><u>{$administrative->name}</u></a>
                            because there was an error with the operation!";
        }
        return redirect()->route('administratives.index')
            ->with('alert-type', $alertType)
            ->with('alert-msg', $alertMsg);
    }

    public function destroyPhoto(User $administrative): RedirectResponse
    {
        if ($administrative->photo_filename) {
            if (Storage::fileExists('public/photos/' . $administrative->photo_filename)) {
                Storage::delete('public/photos/' . $administrative->photo_filename);
            }
            $administrative->photo_filename = null;
            $administrative->save();
            return redirect()->back()
                ->with('alert-type', 'success')
                ->with('alert-msg', "Photo of administrative {$administrative->name} has been deleted.");
        }
        return redirect()->back();
    }
}
