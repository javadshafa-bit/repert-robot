<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Province;
use App\Models\Representative;
use App\Models\Setting;
use App\Support\TenantRule;
use Illuminate\Http\Request;

class RepresentativeController extends Controller {
    public function index(Request $request) {
        $query = Representative::with('province');

        if ($request->filled('province_id')) {
            $query->where('province_id', $request->province_id);
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('first_name', 'like', '%'.$request->search.'%')
                    ->orWhere('last_name', 'like', '%'.$request->search.'%')
                    ->orWhere('phone_number', 'like', '%'.$request->search.'%');
            });
        }

        $representatives = $query->latest()->paginate(20)->withQueryString();
        $provinces       = Province::orderBy('name')->get();

        return view('admin.representatives.index', compact('representatives','provinces'));
    }

    /** آیا طبق تنظیمات سازمان، وارد کردن شماره تماس نماینده اجباری است؟ */
    private function phoneRequired(): bool {
        return Setting::get('require_representative_phone', '1') === '1';
    }

    public function create() {
        $provinces    = Province::orderBy('name')->get();
        $phoneRequired = $this->phoneRequired();
        return view('admin.representatives.create', compact('provinces', 'phoneRequired'));
    }

    public function store(Request $request) {
        $request->merge(['phone_number' => $this->normalizePhone($request->input('phone_number'))]);

        $request->validate([
            'province_id'  => ['required', TenantRule::exists('provinces')],
            'first_name'   => 'required|string|max:100',
            'last_name'    => 'required|string|max:100',
            'phone_number' => [$this->phoneRequired() ? 'required' : 'nullable', 'string', TenantRule::unique('representatives', 'phone_number')],
        ], [
            'province_id.required'       => 'استان الزامی است.',
            'first_name.required'        => 'نام الزامی است.',
            'last_name.required'         => 'نام خانوادگی الزامی است.',
            'phone_number.required'      => 'شماره تماس الزامی است.',
            'phone_number.unique'        => 'این شماره تماس قبلاً ثبت شده است.',
        ]);

        Representative::create($request->only('province_id','first_name','last_name','phone_number'));
        return redirect()->route('admin.representatives.index')->with('success', 'نماینده با موفقیت اضافه شد.');
    }

    public function edit(Representative $representative) {
        $provinces     = Province::orderBy('name')->get();
        $phoneRequired = $this->phoneRequired();
        return view('admin.representatives.edit', compact('representative','provinces','phoneRequired'));
    }

    public function update(Request $request, Representative $representative) {
        $request->merge(['phone_number' => $this->normalizePhone($request->input('phone_number'))]);

        $request->validate([
            'province_id'  => ['required', TenantRule::exists('provinces')],
            'first_name'   => 'required|string|max:100',
            'last_name'    => 'required|string|max:100',
            'phone_number' => [$this->phoneRequired() ? 'required' : 'nullable', 'string', TenantRule::unique('representatives', 'phone_number')->ignore($representative->id)],
        ], [
            'phone_number.required' => 'شماره تماس الزامی است.',
            'phone_number.unique'   => 'این شماره تماس قبلاً ثبت شده است.',
        ]);

        $representative->update($request->only('province_id','first_name','last_name','phone_number'));
        return redirect()->route('admin.representatives.index')->with('success', 'نماینده با موفقیت ویرایش شد.');
    }

    public function destroy(Representative $representative) {
        $representative->delete();
        return back()->with('success', 'نماینده با موفقیت حذف شد.');
    }

    public function show(Representative $representative) {
        $representative->load(['province', 'reports.category', 'monthlyStatuses']);
        return view('admin.representatives.show', compact('representative'));
    }

    /** رشته‌ی خالی را به null تبدیل می‌کند تا یونیک با چند رکورد بدون شماره تداخل نکند */
    private function normalizePhone($value): ?string {
        $value = is_string($value) ? trim($value) : $value;
        return ($value === '' || $value === null) ? null : $value;
    }
}
