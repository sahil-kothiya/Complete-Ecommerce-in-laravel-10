<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Filter;
use Illuminate\Support\Facades\Log;

class FilterController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $filters = Filter::orderBy('title', 'ASC')->paginate(10);
        return view('backend.filter.index', compact('filters'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('backend.filter.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|unique:filters,name|max:255',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);

        try {
            Filter::create($validatedData);
            return redirect()->route('filter.index')->with('success', 'Filter created successfully.');
        } catch (\Exception $e) {
            Log::error('Error creating filter: ' . $e->getMessage());
            return back()->with('error', 'Failed to create filter. Please try again.');
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $filter = Filter::findOrFail($id);
        return view('backend.filter.edit', compact('filter'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $filter = Filter::findOrFail($id);

        $validatedData = $request->validate([
            'name' => 'required|string|unique:filters,name,' . $id . '|max:255',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);

        try {
            $filter->update($validatedData);
            return redirect()->route('filter.index')->with('success', 'Filter updated successfully.');
        } catch (\Exception $e) {
            Log::error('Error updating filter: ' . $e->getMessage());
            return back()->with('error', 'Failed to update filter. Please try again.');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $filter = Filter::findOrFail($id);

        try {
            $filter->delete();
            return redirect()->route('filter.index')->with('success', 'Filter deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Error deleting filter: ' . $e->getMessage());
            return back()->with('error', 'Failed to delete filter. Please try again.');
        }
    }
}
