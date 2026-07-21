<table>
    <thead>
        <tr>
            <th style="font-weight: bold; text-align: center; background-color: #d1d5db;">No</th>
            <th style="font-weight: bold; text-align: center; background-color: #d1d5db;">Tipe</th>
            <th style="font-weight: bold; text-align: center; background-color: #d1d5db;">Periode</th>
            <th style="font-weight: bold; text-align: center; background-color: #d1d5db;">Institusi</th>
            <th style="font-weight: bold; text-align: center; background-color: #d1d5db;">Program Studi</th>
            <th style="font-weight: bold; text-align: center; background-color: #d1d5db;">Kategori</th>
            <th style="font-weight: bold; text-align: center; background-color: #d1d5db;">Peran</th>
            <th style="font-weight: bold; text-align: center; background-color: #d1d5db;">Nama Peserta</th>
            <th style="font-weight: bold; text-align: center; background-color: #d1d5db;">NIM/NISN</th>
            <th style="font-weight: bold; text-align: center; background-color: #d1d5db;">Email</th>
            <th style="font-weight: bold; text-align: center; background-color: #d1d5db;">Telepon (Ketua)</th>
            <th style="font-weight: bold; text-align: center; background-color: #d1d5db;">Status Pendaftaran</th>
            <th style="font-weight: bold; text-align: center; background-color: #d1d5db;">Tanggal Daftar</th>
        </tr>
    </thead>
    <tbody>
        @foreach($submissions as $index => $submission)
            @php
                // Hitung jumlah anggota yang ada
                $members = [];
                for($i = 1; $i <= 10; $i++) {
                    $col = 'member_' . $i;
                    if (!empty($submission->$col)) {
                        $members[] = explode('|', $submission->$col);
                    }
                }
                $memberCount = count($members);
                $isGroup = $memberCount > 1;
                $kategori = $isGroup ? 'Kelompok' : 'Individu';
            @endphp

            @foreach($members as $mIndex => $memberData)
                @php
                    $nama = $memberData[0] ?? '';
                    $nim = $memberData[1] ?? '';
                    $email = $memberData[2] ?? '';
                    $peran = ($mIndex === 0) ? 'Ketua' : 'Anggota';
                @endphp
                <tr>
                    @if($mIndex === 0)
                        <td rowspan="{{ $memberCount }}" style="vertical-align: middle; text-align: center;">{{ $index + 1 }}</td>
                        <td rowspan="{{ $memberCount }}" style="vertical-align: middle; text-align: center;">{{ ucwords(strtolower($submission->type)) }}</td>
                        <td rowspan="{{ $memberCount }}" style="vertical-align: middle; text-align: center;">
                            @if($submission->period)
                                {{ \Carbon\Carbon::parse($submission->period->start_date)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($submission->period->end_date)->format('d/m/Y') }}
                            @else
                                -
                            @endif
                        </td>
                        <td rowspan="{{ $memberCount }}" style="vertical-align: middle;">{{ ucwords(strtolower($submission->institution)) }}</td>
                        <td rowspan="{{ $memberCount }}" style="vertical-align: middle;">{{ ucwords(strtolower($submission->study_program ?? '-')) }}</td>
                        <td rowspan="{{ $memberCount }}" style="vertical-align: middle; text-align: center;">{{ $kategori }}</td>
                    @endif

                    <td>{{ $peran }}</td>
                    <td>{{ ucwords(strtolower($nama)) }}</td>
                    <td>{{ $nim }}</td>
                    <td>{{ $email }}</td>

                    @if($mIndex === 0)
                        <td rowspan="{{ $memberCount }}" style="vertical-align: middle; text-align: center;">{{ "'".$submission->phone_number }}</td>
                        <td rowspan="{{ $memberCount }}" style="vertical-align: middle; text-align: center;">{{ ucwords(strtolower($submission->status)) }}</td>
                        <td rowspan="{{ $memberCount }}" style="vertical-align: middle; text-align: center;">{{ $submission->created_at->format('Y-m-d H:i') }}</td>
                    @endif
                </tr>
            @endforeach
        @endforeach
    </tbody>
</table>
