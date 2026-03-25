export class PachyBaseClient {
  constructor({ baseUrl, token, tenantHeader = "X-Tenant-Id", tenant = null }) {
    this.baseUrl = baseUrl.replace(/\/+$/, "");
    this.token = token;
    this.tenantHeader = tenantHeader;
    this.tenant = tenant;
  }

  async request(path, { method = "GET", body } = {}) {
    const headers = {
      Authorization: `Bearer ${this.token}`,
      "Content-Type": "application/json",
    };

    if (this.tenant) {
      headers[this.tenantHeader] = this.tenant;
    }

    const response = await fetch(`${this.baseUrl}${path}`, {
      method,
      headers,
      body: body ? JSON.stringify(body) : undefined,
    });

    const payload = await response.json();

    if (!response.ok || payload.success === false) {
      throw new Error(payload?.error?.message || `Request failed with ${response.status}`);
    }

    return payload.data;
  }

  provisionProject(input) {
    return this.request("/api/platform/projects", { method: "POST", body: input });
  }

  createBackup(project, label = null) {
    return this.request(`/api/platform/projects/${project}/backups`, {
      method: "POST",
      body: label ? { label } : {},
    });
  }

  putSecret(project, key, value) {
    return this.request(`/api/platform/projects/${project}/secrets/${key}`, {
      method: "PUT",
      body: { value },
    });
  }

  enqueueJob(input) {
    return this.request("/api/platform/jobs", { method: "POST", body: input });
  }

  createWebhook(input) {
    return this.request("/api/platform/webhooks", { method: "POST", body: input });
  }

  uploadFile(input) {
    return this.request("/api/platform/storage", { method: "POST", body: input });
  }
}
