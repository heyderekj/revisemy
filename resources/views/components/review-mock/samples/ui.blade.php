{{-- Ledgerly dashboard. Sized in container units so it scales like a
     screenshot. Positions line up with the marks in config/review-samples. --}}
<div class="@container relative aspect-[16/10] w-full overflow-hidden bg-raised text-zinc-900">
    {{-- Sidebar --}}
    <div class="absolute bottom-0 left-0 top-0 w-[24%] bg-well px-[3%] pt-[3cqw]">
        <p class="flex items-center gap-[0.8cqw] text-[1.7cqw] font-semibold"><span class="size-[2cqw] rounded-[0.5cqw] bg-done"></span>Ledgerly</p>
        <div class="mt-[2.4cqw] space-y-[1.5cqw] text-[1.3cqw] text-zinc-600">
            @foreach (['Overview', 'Accounts', 'Payments', 'Invoices', 'Reports'] as $i => $item)
                <p @class(['flex items-center gap-[1cqw]', 'font-medium text-zinc-900' => $i === 0])>
                    <span @class(['size-[1.5cqw] rounded-[0.4cqw]', 'bg-zinc-900' => $i === 0, 'bg-zinc-300' => $i !== 0])></span>
                    <span>{{ $item }}</span>
                </p>
            @endforeach
        </div>
    </div>

    {{-- Page title --}}
    <div class="absolute left-[28%] right-[3%] top-[5%] flex items-center justify-between">
        <p class="text-[2.2cqw] font-semibold tracking-tight">October</p>
        <span class="rounded-full bg-chip px-[1.4cqw] py-[0.5cqw] text-[1.2cqw] text-zinc-600">Export</span>
    </div>

    {{-- Stat tiles --}}
    <div class="absolute left-[28%] right-[3%] top-[17%] grid h-[18%] grid-cols-3 gap-[1.5cqw]">
        @foreach ([['Balance', '$48,210'], ['In', '$12,400'], ['Out', '$9,870']] as [$label, $value])
            <div class="rounded-[1cqw] bg-well p-[1.4cqw]">
                <p class="text-[1.1cqw] text-zinc-500">{{ $label }}</p>
                <p class="mt-[0.6cqw] text-[2.4cqw] font-semibold tracking-tight">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    {{-- Table --}}
    <div class="absolute left-[28%] right-[3%] top-[42%] text-[1.25cqw]">
        <div class="grid grid-cols-[1.6fr_1fr_1fr] border-b border-border pb-[0.8cqw] text-zinc-400">
            <span>Payee</span><span>Status</span><span class="text-right">Amount</span>
        </div>
        @foreach ([['Northwind Studio', 'Cleared', '1,200.00'], ['Atlas Rent', 'Pending', '3,450.00'], ['Figma', 'Cleared', '45.00'], ['Prairie Payroll', 'Cleared', '5,175.00']] as [$payee, $status, $amount])
            <div class="grid grid-cols-[1.6fr_1fr_1fr] border-b border-border py-[1cqw]">
                <span>{{ $payee }}</span>
                <span class="text-zinc-500">{{ $status }}</span>
                <span class="text-right">{{ $amount }}</span>
            </div>
        @endforeach
    </div>
</div>
