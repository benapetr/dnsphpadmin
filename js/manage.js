function submitDeleteRecord(link) {
  if (!confirm(link.dataset.confirm)) {
    return false;
  }

  const form = document.getElementById("deleteRecordForm");
  form.querySelector("[name=delete]").value = link.dataset.delete || "";
  form.querySelector("[name=ptr]").value = link.dataset.ptr || "";
  form.querySelector("[name=key]").value = link.dataset.key || "";
  form.querySelector("[name=value]").value = link.dataset.value || "";
  form.querySelector("[name=type]").value = link.dataset.type || "";
  form.submit();
  return false;
}
