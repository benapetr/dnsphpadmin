function clearDeleteForm(form) {
  for (const input of form.querySelectorAll("[data-dynamic-delete]")) {
    input.remove();
  }
  form.querySelector("[name=ptr]").value = "";
}

function appendHidden(form, name, value) {
  const input = document.createElement("input");
  input.type = "hidden";
  input.name = name;
  input.value = value;
  input.dataset.dynamicDelete = "true";
  form.appendChild(input);
}

function submitDeleteRecord(link) {
  if (!confirm(link.dataset.confirm)) {
    return false;
  }

  const form = document.getElementById("deleteRecordForm");
  clearDeleteForm(form);
  appendHidden(form, "delete[]", link.dataset.delete || "");
  if (link.dataset.ptr === "true") {
    form.querySelector("[name=ptr]").value = "true";
  }
  form.submit();
  return false;
}

function submitSelectedRecords(deletePtr) {
  const selectedRecords = document.querySelectorAll(".record-select:checked");
  if (selectedRecords.length === 0) {
    alert("No records selected");
    return false;
  }

  const action = deletePtr ? "Delete selected records and their PTR records?" : "Delete selected records?";
  if (!confirm(action + " (" + selectedRecords.length + ")")) {
    return false;
  }

  const form = document.getElementById("deleteRecordForm");
  clearDeleteForm(form);
  form.querySelector("[name=ptr]").value = deletePtr ? "true" : "";

  for (const record of selectedRecords) {
    appendHidden(form, "delete[]", record.value);
  }

  form.submit();
  return false;
}
