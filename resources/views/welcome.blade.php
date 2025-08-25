@extends('shopify-app::layouts.default')

@section('content')
    <p>You are: {{ $shopDomain ?? Auth::user()->name }}</p>

    <ui-title-bar title="Products">
        <button onclick="console.log('Secondary action')">Secondary action</button>
        <button variant="primary" onclick="console.log('Primary action')">Primary action</button>
    </ui-title-bar>

    <div style="margin-top: 20px;">
        <input type="file" id="fileInput" accept=".csv, .xlsx, .xls" />
        <button id="uploadBtn">Upload & Show Data</button>
    </div>

    <table border="1" id="dataTable" style="margin-top: 20px;">
        <thead>
            <tr><th>No data</th></tr>
        </thead>
        <tbody></tbody>
    </table>

    <script>
        document.getElementById("uploadBtn").addEventListener("click", function() {
            let fileInput = document.getElementById("fileInput");
            if (fileInput.files.length === 0) {
                alert("Please select a file first.");
                return;
            }

            let formData = new FormData();
            formData.append("file", fileInput.files[0]);
            formData.append("_token", "{{ csrf_token() }}");

            fetch("{{ route('file.upload') }}", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {

                console.log(data);
                let tableHead = document.querySelector("#dataTable thead tr");
                let tableBody = document.querySelector("#dataTable tbody");

                tableHead.innerHTML = "";
                tableBody.innerHTML = "";

                data.forEach((row, index) => {
                    if (index === 0) {
                        row.forEach(cell => {
                            tableHead.innerHTML += `<th>${cell}</th>`;
                        });
                    } else {
                        let rowHtml = "<tr>";
                        row.forEach(cell => {
                            rowHtml += `<td>${cell ?? ''}</td>`;
                        });
                        rowHtml += "</tr>";
                        tableBody.innerHTML += rowHtml;
                    }
                });
            })
            .catch(error => console.error("Error:", error));
        });
    </script>
@endsection

