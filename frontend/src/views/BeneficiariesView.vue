<template>
  <div class="beneficiary-wrap">
    <header class="view-header no-print">
      <div>
        <div class="view-title serif">Member Beneficiaries</div>
        <div class="view-sub">Primary and secondary beneficiaries, allocation checks, guardians, and declaration PDF</div>
      </div>
      <div class="header-actions">
        <button class="btn btn-secondary" @click="loadData">Refresh</button>
        <button class="btn btn-primary" :disabled="!selectedMember" @click="printDeclaration">Print Declaration</button>
      </div>
    </header>

    <main class="beneficiary-body">
      <aside class="member-panel no-print">
        <div class="panel-title">Members</div>
        <div class="member-search">
          <input v-model.trim="searchTerm" class="form-input search-input" type="search" placeholder="Search member or beneficiary" />
        </div>
        <div class="member-list">
          <button
            v-for="member in filteredMembers"
            :key="member.id"
            :class="['member-row', selectedMember?.id === member.id && 'active']"
            @click="selectMember(member)"
          >
            <div>
              <div class="row-title">{{ member.first_name }} {{ member.last_name }}</div>
              <div class="row-sub">{{ member.member_no }} · {{ member.department || 'No department' }}</div>
            </div>
            <span :class="['status-dot', complianceFor(member.id).ok && 'ok']"></span>
          </button>
          <div v-if="!filteredMembers.length" class="empty-inline">No matching members</div>
        </div>
      </aside>

      <section class="beneficiary-workspace">
        <template v-if="selectedMember">
          <section class="beneficiary-grid no-print">
            <form class="entry-card" @submit.prevent="saveBeneficiary">
              <div class="entry-card-head">
                <div class="panel-title inline">{{ editingId ? 'Edit Beneficiary' : 'Add Beneficiary' }}</div>
                <div class="selected-chip">
                  <strong>{{ selectedMember.first_name }} {{ selectedMember.last_name }}</strong>
                  <span>{{ selectedMember.member_no }}</span>
                </div>
              </div>
              <div v-if="selectedRows.length" class="form-group existing-beneficiary-picker">
                <label class="form-label">Existing Beneficiary</label>
                <select v-model="form.existing_id" class="form-select" @change="chooseExistingBeneficiary">
                  <option value="">Create new beneficiary</option>
                  <option v-for="row in selectedRows" :key="row.id" :value="row.id">
                    {{ row.name }} · {{ row.type }} · {{ row.share }}%
                  </option>
                </select>
              </div>
              <div class="form-grid">
                <div class="form-group">
                  <label class="form-label">Beneficiary Type</label>
                  <select v-model="form.type" class="form-select">
                    <option value="primary">Primary</option>
                    <option value="secondary">Secondary</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Allocation %</label>
                  <input v-model.number="form.share" class="form-input" type="number" min="0" max="100" step="1" />
                </div>
                <div class="form-group">
                  <label class="form-label">Full Name</label>
                  <input v-model.trim="form.name" class="form-input" placeholder="Beneficiary name" />
                </div>
                <div class="form-group">
                  <label class="form-label">Relationship</label>
                  <select v-model="form.relationship" class="form-select">
                    <option>Spouse</option>
                    <option>Child</option>
                    <option>Parent</option>
                    <option>Sibling</option>
                    <option>Other</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Birth Date</label>
                  <input v-model="form.birth_date" class="form-input" type="date" />
                </div>
                <div class="form-group">
                  <label class="form-label">Contact</label>
                  <input v-model.trim="form.contact" class="form-input" placeholder="Mobile number" />
                </div>
                <div class="form-group wide">
                  <label class="form-label">Address</label>
                  <input v-model.trim="form.address" class="form-input" placeholder="Complete address" />
                </div>
                <div class="form-group">
                  <label class="form-label">Type of ID</label>
                  <select v-model="form.id_type" class="form-select">
                    <option>PhilSys ID</option>
                    <option>Passport</option>
                    <option>Driver's License</option>
                    <option>UMID</option>
                    <option>SSS ID</option>
                    <option>Company ID</option>
                    <option>School ID</option>
                    <option>Other</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">ID Number</label>
                  <input v-model.trim="form.id_number" class="form-input" placeholder="ID number" />
                </div>
                <div class="form-group wide">
                  <label class="form-label">Registered Name on ID</label>
                  <input v-model.trim="form.registered_name" class="form-input" placeholder="Name exactly as shown on ID" />
                </div>
                <div class="form-group wide">
                  <label class="form-label">Guardian for Minor</label>
                  <input v-model.trim="form.guardian" class="form-input" placeholder="Required if beneficiary is below 18" />
                </div>
              </div>
              <textarea v-model.trim="form.remarks" class="form-textarea" placeholder="Remarks"></textarea>
              <div class="form-actions">
                <button v-if="editingId" class="btn btn-secondary" type="button" @click="resetForm">Cancel</button>
                <button class="btn btn-primary" type="submit">{{ editingId ? 'Update Beneficiary' : 'Add Beneficiary' }}</button>
              </div>
            </form>

            <section class="compliance-card">
              <div class="panel-title inline">Compliance Checks</div>
              <div class="check-list">
                <div v-for="check in selectedCompliance.checks" :key="check.label" :class="['check-row', check.ok && 'ok']">
                  <span>{{ check.ok ? '✓' : '!' }}</span>
                  <div>
                    <strong>{{ check.label }}</strong>
                    <small>{{ check.text }}</small>
                  </div>
                </div>
              </div>
            </section>
          </section>

          <section class="list-card no-print">
            <div class="beneficiary-columns">
              <BeneficiaryList title="Primary Beneficiaries" :rows="primaryRows" @edit="editBeneficiary" @delete="deleteBeneficiary" @attach-id="attachBeneficiaryId" @preview-id="previewBeneficiaryId" />
              <BeneficiaryList title="Secondary Beneficiaries" :rows="secondaryRows" @edit="editBeneficiary" @delete="deleteBeneficiary" @attach-id="attachBeneficiaryId" @preview-id="previewBeneficiaryId" />
            </div>
          </section>

          <section class="attachment-card no-print">
            <div>
              <div class="panel-title inline">Signed Declaration Attachment</div>
              <p class="attachment-help">Attach the scanned signed beneficiary declaration after the member signs the printed form.</p>
            </div>
            <label class="attachment-drop">
              <input type="file" accept="application/pdf,image/*" @change="attachSignedDeclaration" />
              <span>{{ selectedAttachment ? 'Replace signed document' : 'Upload signed PDF or image' }}</span>
              <small>Accepted: PDF, JPG, PNG</small>
            </label>
            <div v-if="selectedAttachment" class="attachment-file">
              <div>
                <strong>{{ selectedAttachment.name }}</strong>
                <span>{{ selectedAttachment.type || 'Uploaded file' }} · {{ selectedAttachment.uploaded_at }}</span>
              </div>
              <div class="attachment-actions">
                <a v-if="selectedAttachment.data_url" class="btn btn-secondary btn-sm" :href="selectedAttachment.data_url" :download="selectedAttachment.name">Download</a>
                <button class="btn btn-secondary btn-sm" type="button" @click="removeSignedDeclaration">Remove</button>
              </div>
            </div>
          </section>

          <section class="declaration-card">
            <div class="pdf-paper declaration-paper">
              <div class="declaration-header">
                <h1>CRS HOLDINGS CORPORATIONS EMPLOYEES CREDIT COOPERATIVE</h1>
                <div class="pdf-address">A.C. Cortes Avenue, Alang-alang, Mandaue City, Cebu 6014</div>
                <h2>BENEFICIARY DECLARATION</h2>
              </div>
              <div class="pdf-grid-2">
                <div>Member No.: <span class="pdf-field">{{ selectedMember.member_no }}</span></div>
                <div>Name: <span class="pdf-field">{{ memberFullName }}</span></div>
                <div>Company: <span class="pdf-field">{{ selectedMember.company }}</span></div>
                <div>Department: <span class="pdf-field">{{ selectedMember.department }}</span></div>
              </div>

              <div class="pdf-section-title">Primary Beneficiaries</div>
              <DeclarationTable :rows="primaryRows" />
              <div class="pdf-section-title">Secondary Beneficiaries</div>
              <DeclarationTable :rows="secondaryRows" />

              <div class="legal-block">
                <p>
                  I, <span class="pdf-field legal-name">{{ memberFullName }}</span>, of legal age and a member of CRS Holdings Corporations
                  Employees Credit Cooperative, hereby designate the person(s) named above as my beneficiary/beneficiaries for cooperative records,
                  claims, and benefit processing, subject to the Cooperative's by-laws, policies, validation procedures, and applicable law.
                </p>
                <p>
                  I certify that the information stated in this declaration is true, complete, and voluntarily given. I understand that this form
                  supersedes prior beneficiary declarations on file once accepted by the Cooperative, and that I remain responsible for updating this
                  record should my civil status, dependents, contact information, or beneficiary instructions change.
                </p>
                <p>
                  By signing below, I acknowledge that I have read and understood this declaration, authorize the Cooperative to keep and process the
                  information herein for legitimate membership and benefit administration purposes, and confirm that no person forced, intimidated,
                  or unduly influenced me in making this designation.
                </p>
              </div>
              <div class="attestation-line">
                Signed this <span></span> day of <span></span>, 20<span></span> at Mandaue City, Cebu.
              </div>
              <div class="signature-grid">
                <div><div class="pdf-sig-line"></div><span>Member Signature over Printed Name / Date</span></div>
                <div><div class="pdf-sig-line"></div><span>COOP Authorized Representative / Date Received</span></div>
              </div>
              <div class="witness-grid">
                <div><div class="pdf-sig-line"></div><span>Witness</span></div>
                <div><div class="pdf-sig-line"></div><span>Processed By</span></div>
              </div>
            </div>
          </section>
        </template>

        <div v-else class="empty-state">
          <div class="empty-icon">♡</div>
          <div class="empty-title">Select a member</div>
          <div class="text-muted">Choose a member to manage beneficiaries and print a declaration.</div>
        </div>
      </section>
    </main>
  </div>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, reactive, ref } from 'vue'
import { api } from '../composables/useApi'
import { useToast } from '../composables/useToast'


const BeneficiaryList = defineComponent({
  props: {
    title: { type: String, required: true },
    rows: { type: Array, required: true },
  },
  emits: ['edit', 'delete', 'attach-id', 'preview-id'],
  setup(props, { emit }) {
    const initialsFor = (name = '') => name.split(' ').filter(Boolean).slice(0, 2).map(part => part[0]).join('').toUpperCase() || 'BN'
    const totalShare = () => props.rows.reduce((sum, row) => sum + Number(row.share || 0), 0)

    return () => h('section', { class: 'beneficiary-list-card' }, [
      h('div', { class: 'list-title' }, [
        h('div', null, props.title),
        h('span', `${props.rows.length} encoded · ${totalShare()}% allocation`),
      ]),
      props.rows.length
        ? h('div', { class: 'beneficiary-list' }, props.rows.map(row => h('div', { class: 'beneficiary-row', key: row.id }, [
            h('div', { class: 'beneficiary-identity' }, [
              h('div', { class: 'beneficiary-avatar' }, initialsFor(row.name)),
              h('div', { class: 'beneficiary-copy' }, [
                h('div', { class: 'row-title' }, row.name),
                h('div', { class: 'beneficiary-meta' }, [
                  h('span', row.relationship || 'Relationship not set'),
                  h('span', row.contact || 'No contact'),
                  h('span', row.id_type && row.id_number ? `${row.id_type}: ${row.id_number}` : 'No ID details'),
                ]),
                row.registered_name ? h('div', { class: 'id-name-chip' }, `ID name: ${row.registered_name}`) : null,
                row.guardian ? h('div', { class: 'guardian-chip' }, `Guardian: ${row.guardian}`) : null,
              ]),
            ]),
            h('div', { class: 'beneficiary-actions' }, [
              h('strong', { class: 'share-chip' }, `${row.share}%`),
              h('div', { class: 'id-attachment-actions' }, [
                h('label', { class: 'btn btn-secondary btn-sm table-file-btn' }, [
                  h('input', {
                    type: 'file',
                    accept: 'application/pdf,image/*',
                    onChange: event => emit('attach-id', row, event),
                  }),
                  row.id_attachment?.name ? 'Replace ID' : 'Attach ID',
                ]),
                h('button', {
                  class: 'btn btn-secondary btn-sm',
                  type: 'button',
                  disabled: !row.id_attachment?.data_url,
                  onClick: () => emit('preview-id', row),
                }, 'Preview ID'),
              ]),
              h('div', { class: 'action-pair' }, [
                h('button', { class: 'btn btn-secondary btn-sm', type: 'button', onClick: () => emit('edit', row) }, 'Edit'),
                h('button', { class: 'btn btn-secondary btn-sm', type: 'button', onClick: () => emit('delete', row) }, 'Delete'),
              ]),
            ]),
          ])))
        : h('div', { class: 'empty-inline beneficiary-empty' }, 'No beneficiaries encoded yet'),
    ])
  },
})

const DeclarationTable = defineComponent({
  props: { rows: { type: Array, required: true } },
  setup(props) {
    return () => h('table', null, [
      h('thead', null, h('tr', null, ['Name', 'Relationship', 'Contact', 'Share', 'Guardian'].map(label => h('th', label)))),
      h('tbody', null, props.rows.length
        ? props.rows.map(row => h('tr', { key: row.id }, [
            h('td', row.name),
            h('td', row.relationship),
            h('td', row.contact || '-'),
            h('td', `${row.share}%`),
            h('td', row.guardian || '-'),
          ]))
        : [h('tr', null, [h('td', { colspan: 5 }, 'No beneficiaries encoded')])]),
    ])
  },
})

const { success, error } = useToast()
const members = ref([])
const records = ref([])
const signedAttachments = ref({})
const selectedMember = ref(null)
const searchTerm = ref('')
const editingId = ref(null)

const form = reactive({
  existing_id: '',
  type: 'primary',
  name: '',
  relationship: 'Spouse',
  share: 100,
  birth_date: '',
  contact: '',
  address: '',
  id_type: 'PhilSys ID',
  id_number: '',
  registered_name: '',
  guardian: '',
  remarks: '',
})

const filteredMembers = computed(() => {
  const query = searchTerm.value.toLowerCase()
  if (!query) return members.value
  return members.value.filter(member => {
    const memberMatch = [
      member.member_no,
      member.first_name,
      member.last_name,
      `${member.first_name} ${member.last_name}`,
      member.department,
      member.position,
    ].some(value => String(value || '').toLowerCase().includes(query))
    const beneficiaryMatch = records.value
      .filter(row => row.member_id === member.id)
      .some(row => [row.name, row.relationship, row.contact].some(value => String(value || '').toLowerCase().includes(query)))
    return memberMatch || beneficiaryMatch
  })
})

const selectedRows = computed(() => records.value.filter(row => row.member_id === selectedMember.value?.id))
const primaryRows = computed(() => selectedRows.value.filter(row => row.type === 'primary'))
const secondaryRows = computed(() => selectedRows.value.filter(row => row.type === 'secondary'))
const primaryTotal = computed(() => primaryRows.value.reduce((sum, row) => sum + Number(row.share || 0), 0))
const secondaryTotal = computed(() => secondaryRows.value.reduce((sum, row) => sum + Number(row.share || 0), 0))
const memberFullName = computed(() => selectedMember.value ? `${selectedMember.value.first_name} ${selectedMember.value.middle_name || ''} ${selectedMember.value.last_name}`.replace(/\s+/g, ' ').trim() : '')
const selectedCompliance = computed(() => complianceFor(selectedMember.value?.id))
const selectedAttachment = computed(() => selectedMember.value ? signedAttachments.value[selectedMember.value.id] : null)

function isMinor(row) {
  if (!row.birth_date) return false
  const birth = new Date(row.birth_date)
  const today = new Date()
  const age = today.getFullYear() - birth.getFullYear() - (today < new Date(today.getFullYear(), birth.getMonth(), birth.getDate()) ? 1 : 0)
  return age < 18
}

function complianceFor(memberId) {
  const rows = records.value.filter(row => row.member_id === memberId)
  const primary = rows.filter(row => row.type === 'primary')
  const secondary = rows.filter(row => row.type === 'secondary')
  const primaryShare = primary.reduce((sum, row) => sum + Number(row.share || 0), 0)
  const secondaryShare = secondary.reduce((sum, row) => sum + Number(row.share || 0), 0)
  const missingGuardian = rows.filter(row => isMinor(row) && !row.guardian).length
  const checks = [
    { label: 'Primary beneficiaries encoded', ok: primary.length > 0, text: primary.length ? `${primary.length} primary record(s)` : 'At least one primary beneficiary is required.' },
    { label: 'Primary allocation equals 100%', ok: primaryShare === 100, text: `Current primary allocation is ${primaryShare}%.` },
    { label: 'Secondary allocation valid', ok: !secondary.length || secondaryShare === 100, text: secondary.length ? `Current secondary allocation is ${secondaryShare}%.` : 'Secondary beneficiaries are optional.' },
    { label: 'Minor guardian details', ok: missingGuardian === 0, text: missingGuardian ? `${missingGuardian} minor beneficiary record(s) need guardian details.` : 'Guardian details complete where needed.' },
  ]
  return { ok: checks.every(check => check.ok), checks }
}

// Normalize API record (DB column names) to legacy view field names used by template/computeds
function normalizeBeneficiary(rec) {
  return {
    ...rec,
    name: rec.full_name ?? rec.name,
    type: rec.beneficiary_type ?? rec.type,
    share: rec.allocation_percent != null ? Number(rec.allocation_percent) : Number(rec.share || 0),
  }
}

function seedBeneficiaries(memberRows) {
  return memberRows.flatMap((member, index) => {
    const baseId = (index + 1) * 100
    return [
      {
        id: baseId + 1,
        member_id: member.id,
        type: 'primary',
        name: `${member.first_name} ${member.last_name} Jr.`,
        relationship: 'Child',
        share: 60,
        birth_date: '2012-06-10',
        contact: member.contact,
        address: member.address,
        id_type: 'School ID',
        id_number: `SCH-${member.member_no}`,
        registered_name: `${member.first_name} ${member.last_name} Jr.`,
        guardian: `${member.first_name} ${member.last_name}`,
        remarks: 'Seeded preview record',
      },
      {
        id: baseId + 2,
        member_id: member.id,
        type: 'primary',
        name: `Ana ${member.last_name}`,
        relationship: 'Spouse',
        share: 40,
        birth_date: '1988-02-14',
        contact: '09170000001',
        address: member.address,
        id_type: 'PhilSys ID',
        id_number: `PSN-${baseId + 2}`,
        registered_name: `Ana ${member.last_name}`,
        guardian: '',
        remarks: 'Seeded preview record',
      },
      {
        id: baseId + 3,
        member_id: member.id,
        type: 'secondary',
        name: `Roberto ${member.last_name}`,
        relationship: 'Sibling',
        share: 100,
        birth_date: '1985-08-03',
        contact: '09170000002',
        address: member.address,
        id_type: 'Driver\'s License',
        id_number: `DL-${baseId + 3}`,
        registered_name: `Roberto ${member.last_name}`,
        guardian: '',
        remarks: 'Seeded preview record',
      },
    ]
  })
}

async function migrateLocalStorage() {
  const BENEFICIARY_KEY = 'crs-coop-preview-beneficiaries'
  const BENEFICIARY_ATTACHMENT_KEY = 'crs-coop-preview-beneficiary-attachments'
  localStorage.removeItem(BENEFICIARY_ATTACHMENT_KEY) // D-04: always discard base64 ID attachment blobs
  const raw = localStorage.getItem(BENEFICIARY_KEY)
  if (!raw) return
  let records_ls
  try { records_ls = JSON.parse(raw) } catch { localStorage.removeItem(BENEFICIARY_KEY); return }
  if (!Array.isArray(records_ls) || records_ls.length === 0) { localStorage.removeItem(BENEFICIARY_KEY); return }
  for (const rec of records_ls) {
    try {
      await api.createBeneficiary({
        member_id: rec.member_id,
        full_name: rec.name,           // localStorage key 'name' → API 'full_name'
        beneficiary_type: rec.type,    // localStorage key 'type' → API 'beneficiary_type'
        allocation_percent: rec.share, // localStorage key 'share' → API 'allocation_percent'
        relationship: rec.relationship,
        contact: rec.contact,
        birth_date: rec.birth_date,
        address: rec.address,
        id_type: rec.id_type,
        id_number: rec.id_number,
        registered_name: rec.registered_name,
        guardian: rec.guardian,
        remarks: rec.remarks,
        // id_attachment intentionally omitted per D-04: base64 blob not stored in DB
      })
    } catch (e) { console.warn('[migration] beneficiary skipped:', rec.name, e?.message) }
  }
  localStorage.removeItem(BENEFICIARY_KEY) // clear regardless of per-record outcome
}

async function selectMember(member) {
  selectedMember.value = member
  records.value = (await api.getBeneficiaries(member.id)).map(normalizeBeneficiary)
  resetForm()
}

function resetForm() {
  editingId.value = null
  Object.assign(form, {
    existing_id: '',
    type: 'primary',
    name: '',
    relationship: 'Spouse',
    share: 100,
    birth_date: '',
    contact: '',
    address: selectedMember.value?.address || '',
    id_type: 'PhilSys ID',
    id_number: '',
    registered_name: '',
    guardian: '',
    remarks: '',
  })
}

function chooseExistingBeneficiary() {
  if (!form.existing_id) {
    resetForm()
    return
  }
  const row = selectedRows.value.find(item => String(item.id) === String(form.existing_id))
  if (row) editBeneficiary(row)
}

async function saveBeneficiary() {
  if (!selectedMember.value) return error('Select a member first.')
  if (!form.name) return error('Enter the beneficiary name.')
  if (!form.share || Number(form.share) <= 0) return error('Enter a valid allocation percentage.')

  const payload = {
    member_id: selectedMember.value.id,
    full_name: form.name,
    beneficiary_type: form.type,
    allocation_percent: Number(form.share),
    relationship: form.relationship,
    birth_date: form.birth_date,
    contact: form.contact,
    address: form.address,
    id_type: form.id_type,
    id_number: form.id_number,
    registered_name: form.registered_name || form.name,
    guardian: form.guardian,
    remarks: form.remarks,
  }

  try {
    if (editingId.value) {
      await api.updateBeneficiary(editingId.value, payload)
      success('Beneficiary updated.')
    } else {
      await api.createBeneficiary(payload)
      success('Beneficiary added.')
    }
    records.value = (await api.getBeneficiaries(selectedMember.value.id)).map(normalizeBeneficiary)
    resetForm()
  } catch (e) {
    error(e?.message || 'Could not save beneficiary.')
  }
}

function editBeneficiary(row) {
  editingId.value = row.id
  Object.assign(form, {
    existing_id: row.id,
    type: row.type || 'primary',
    name: row.name || '',
    relationship: row.relationship || 'Spouse',
    share: Number(row.share || 0),
    birth_date: row.birth_date || '',
    contact: row.contact || '',
    address: row.address || '',
    id_type: row.id_type || 'PhilSys ID',
    id_number: row.id_number || '',
    registered_name: row.registered_name || row.name || '',
    guardian: row.guardian || '',
    remarks: row.remarks || '',
  })
}

async function deleteBeneficiary(row) {
  try {
    await api.deleteBeneficiary(row.id)
    records.value = (await api.getBeneficiaries(selectedMember.value.id)).map(normalizeBeneficiary)
    success('Beneficiary deleted.')
  } catch (e) {
    error(e?.message || 'Could not delete beneficiary.')
  }
}

function attachBeneficiaryId(row, event) {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file) return
  const reader = new FileReader()
  reader.onload = () => {
    const index = records.value.findIndex(item => item.id === row.id)
    if (index === -1) return
    records.value[index] = {
      ...records.value[index],
      id_attachment: {
        name: file.name,
        type: file.type || 'Uploaded ID',
        size: file.size,
        uploaded_at: new Date().toLocaleString(),
        data_url: reader.result,
      },
    }
    // id_attachment stored in-memory only (D-04: base64 blob not persisted to DB or localStorage)
    success('Beneficiary ID attached.')
  }
  reader.onerror = () => error('Could not read the selected ID file.')
  reader.readAsDataURL(file)
}

function previewBeneficiaryId(row) {
  if (!row.id_attachment?.data_url) return error('Attach an ID file first.')
  const preview = window.open('', '_blank', 'noopener,noreferrer')
  if (!preview) return error('Allow pop-ups to preview the ID.')
  const safeName = row.id_attachment.name || 'Beneficiary ID'
  if (String(row.id_attachment.type || '').includes('pdf')) {
    preview.document.write(`<title>${safeName}</title><iframe src="${row.id_attachment.data_url}" style="width:100%;height:100vh;border:0"></iframe>`)
  } else {
    preview.document.write(`<title>${safeName}</title><body style="margin:0;display:grid;place-items:center;background:#111"><img src="${row.id_attachment.data_url}" alt="${safeName}" style="max-width:100%;max-height:100vh"/></body>`)
  }
  preview.document.close()
}

function loadAttachments() {
  // Note: BENEFICIARY_ATTACHMENT_KEY used here only for signed declaration PDFs (not ID attachments)
  // migrateLocalStorage() will removeItem this key on first load per D-04
  try {
    signedAttachments.value = JSON.parse(localStorage.getItem('crs-coop-preview-beneficiary-attachments') || '{}')
  } catch {
    signedAttachments.value = {}
  }
}

function saveAttachments() {
  localStorage.setItem('crs-coop-preview-beneficiary-attachments', JSON.stringify(signedAttachments.value))
}

function attachSignedDeclaration(event) {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file || !selectedMember.value) return
  const reader = new FileReader()
  reader.onload = () => {
    signedAttachments.value = {
      ...signedAttachments.value,
      [selectedMember.value.id]: {
        name: file.name,
        type: file.type || 'Uploaded file',
        size: file.size,
        uploaded_at: new Date().toLocaleString(),
        data_url: reader.result,
      },
    }
    saveAttachments()
    success('Signed declaration attached.')
  }
  reader.onerror = () => error('Could not read the selected file.')
  reader.readAsDataURL(file)
}

function removeSignedDeclaration() {
  if (!selectedMember.value) return
  const next = { ...signedAttachments.value }
  delete next[selectedMember.value.id]
  signedAttachments.value = next
  saveAttachments()
  success('Signed declaration attachment removed.')
}

function printDeclaration() {
  document.body.classList.add('printing-beneficiary')
  const cleanup = () => document.body.classList.remove('printing-beneficiary')
  window.addEventListener('afterprint', cleanup, { once: true })
  window.print()
  setTimeout(cleanup, 800)
}

async function loadData() {
  loadAttachments()
  members.value = await api.getMembers()
  await migrateLocalStorage()
  selectedMember.value = members.value.find(member => member.id === selectedMember.value?.id) || members.value[0] || null
  if (selectedMember.value) {
    records.value = (await api.getBeneficiaries(selectedMember.value.id)).map(normalizeBeneficiary)
  }
  resetForm()
}

onMounted(loadData)
</script>

<style scoped>
.beneficiary-wrap { height:100%; display:flex; flex-direction:column; overflow:hidden; }
.view-header {
  padding:20px 28px;
  border-bottom:1px solid var(--coop-border);
  display:flex;
  justify-content:space-between;
  align-items:center;
  background:#fff;
}
.view-title { font-size:clamp(34px,3.1vw,48px); color:#202838; }
.view-sub { font-size:clamp(15px,1.2vw,19px); color:#6D7484; margin-top:12px; }
.header-actions { display:flex; gap:10px; align-items:center; }
.search-input { width:300px; }
.beneficiary-body { flex:1; min-height:0; overflow:hidden; display:grid; grid-template-columns:320px minmax(0, 1fr); }
.member-panel { background:#fff; border-right:1px solid var(--coop-border); min-height:0; display:flex; flex-direction:column; }
.member-search { padding:12px 14px; border-bottom:1px solid var(--coop-border); background:#F8FAFC; }
.member-search .search-input { width:100%; }
.panel-title { padding:14px 16px; border-bottom:1px solid var(--coop-border); color:var(--coop-cream); font-size:16px; font-weight:900; }
.panel-title.inline { padding:0 0 14px; border-bottom:0; }
.member-list { padding:10px; display:flex; flex-direction:column; gap:8px; overflow:auto; }
.member-row {
  border:1px solid var(--coop-border);
  background:#fff;
  border-radius:8px;
  padding:12px;
  display:flex;
  justify-content:space-between;
  gap:10px;
  text-align:left;
  cursor:pointer;
}
.member-row:hover, .member-row.active { background:var(--coop-red-dim); border-color:rgba(192,57,43,.25); }
.member-row.active { box-shadow:inset 4px 0 0 var(--coop-red); }
.status-dot { width:10px; height:10px; border-radius:50%; background:var(--coop-red); margin-top:4px; flex-shrink:0; }
.status-dot.ok { background:var(--status-approved); }
.row-title { color:var(--coop-cream); font-weight:900; }
.row-sub { color:var(--coop-muted); font-size:12px; margin-top:2px; }
.beneficiary-workspace { min-width:0; overflow:auto; padding:18px 22px 30px; background:#F3F5F8; }
.entry-card, .compliance-card, .list-card, .attachment-card, .declaration-card {
  background:#fff;
  border:1px solid var(--coop-border);
  border-radius:8px;
  box-shadow:0 8px 22px rgba(31,41,55,.04);
}
.beneficiary-grid { display:grid; grid-template-columns:minmax(0, 1.35fr) minmax(280px, .85fr); gap:14px; margin-bottom:14px; }
.entry-card, .compliance-card, .list-card, .attachment-card, .declaration-card { padding:16px; }
.entry-card-head {
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:12px;
  margin-bottom:12px;
}
.entry-card-head .panel-title.inline { padding:0; }
.selected-chip {
  border:1px solid rgba(192,57,43,.2);
  border-radius:999px;
  background:var(--coop-red-dim);
  color:var(--coop-red);
  padding:7px 10px;
  display:flex;
  align-items:center;
  gap:8px;
  min-width:0;
  max-width:58%;
  white-space:nowrap;
}
.selected-chip strong {
  overflow:hidden;
  text-overflow:ellipsis;
  color:var(--coop-red);
  font-size:12px;
}
.selected-chip span {
  color:var(--coop-muted);
  font-family:var(--font-mono);
  font-size:11px;
  flex-shrink:0;
}
.existing-beneficiary-picker {
  margin-bottom:12px;
}
.form-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:12px; margin-bottom:12px; }
.form-group.wide { grid-column:1 / -1; }
.form-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:12px; }
.check-list { display:flex; flex-direction:column; gap:8px; }
.check-row {
  border:1px solid rgba(192,57,43,.22);
  background:#fff7f6;
  border-radius:8px;
  padding:12px;
  display:flex;
  gap:10px;
}
.check-row.ok { border-color:rgba(39,174,96,.25); background:rgba(39,174,96,.08); }
.check-row > span { font-weight:900; color:var(--coop-red); }
.check-row.ok > span { color:var(--status-approved); }
.check-row strong { display:block; color:var(--coop-cream); }
.check-row small { color:var(--coop-muted); }
.beneficiary-columns { display:grid; grid-template-columns:1fr; gap:16px; align-items:start; }
:deep(.beneficiary-list-card) { background:#fff; border:1px solid var(--coop-border); border-radius:12px; overflow:hidden; box-shadow:0 12px 28px rgba(31,41,55,.06); }
:deep(.list-title) { padding:14px 16px; border-bottom:1px solid var(--coop-border); font-weight:900; color:var(--coop-cream); background:linear-gradient(180deg,#fff,#F8FAFC); display:flex; justify-content:space-between; align-items:center; gap:12px; }
:deep(.list-title span) { color:var(--coop-muted); font-size:11px; font-family:var(--font-sans); font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
:deep(.beneficiary-list) { display:flex; flex-direction:column; gap:8px; padding:12px; }
:deep(.beneficiary-row) { padding:12px; border:1px solid var(--coop-border); border-radius:10px; display:grid; grid-template-columns:minmax(0, 1fr) auto; gap:16px; align-items:center; background:#fff; box-shadow:0 8px 20px rgba(31,41,55,.045); }
:deep(.beneficiary-identity) { display:flex; align-items:center; gap:12px; min-width:0; }
:deep(.beneficiary-avatar) { width:42px; height:42px; border-radius:50%; display:grid; place-items:center; flex:0 0 auto; background:var(--coop-red-dim); color:var(--coop-red); font-weight:900; border:1px solid rgba(178,63,48,.18); }
:deep(.beneficiary-copy) { min-width:0; }
:deep(.beneficiary-meta) { display:flex; flex-wrap:wrap; gap:6px; margin-top:6px; }
:deep(.beneficiary-meta span), :deep(.guardian-chip), :deep(.id-name-chip) { display:inline-flex; align-items:center; border:1px solid var(--coop-border); border-radius:999px; padding:4px 8px; color:var(--coop-muted); background:#F8FAFC; font-size:12px; font-weight:700; }
:deep(.guardian-chip) { margin-top:8px; color:#7A4B13; background:#FFF7E6; border-color:#F5D8A8; }
:deep(.id-name-chip) { margin-top:8px; color:#36556F; background:#EDF6FF; border-color:#C9DFF5; }
:deep(.beneficiary-actions) { display:flex; align-items:center; gap:10px; flex-shrink:0; }
:deep(.share-chip) { display:inline-flex; min-width:54px; justify-content:center; padding:6px 10px; border-radius:999px; color:var(--coop-red); background:var(--coop-red-dim); font-family:var(--font-mono); font-weight:900; }
:deep(.action-pair) { display:flex; gap:6px; }
:deep(.id-attachment-actions) { display:flex; gap:6px; align-items:center; }
:deep(.table-file-btn) { cursor:pointer; }
:deep(.table-file-btn input) { display:none; }
:deep(.beneficiary-empty) { margin:12px; border:1px dashed var(--coop-border); border-radius:10px; background:#F8FAFC; }
.attachment-card { margin-top:14px; display:grid; grid-template-columns:minmax(0, 1fr) 320px; gap:16px; align-items:center; }
.attachment-help { color:var(--coop-muted); margin-top:-6px; }
.attachment-drop { border:1px dashed rgba(192,57,43,.35); border-radius:10px; background:var(--coop-red-dim); min-height:82px; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; cursor:pointer; color:var(--coop-red); font-weight:900; }
.attachment-drop input { display:none; }
.attachment-drop small { color:var(--coop-muted); font-weight:700; margin-top:4px; }
.attachment-file { grid-column:1 / -1; border:1px solid var(--coop-border); border-radius:10px; padding:12px; display:flex; justify-content:space-between; gap:12px; align-items:center; background:#F8FAFC; }
.attachment-file strong { display:block; color:var(--coop-cream); }
.attachment-file span { display:block; color:var(--coop-muted); font-size:12px; margin-top:2px; }
.attachment-actions { display:flex; gap:8px; flex-shrink:0; }
.declaration-card { margin-top:14px; display:flex; justify-content:center; background:#E9EDF3; }
.declaration-paper { width:816px; max-width:100%; min-height:900px; box-shadow:0 14px 35px rgba(31,41,55,.12); overflow:hidden; }
.declaration-header { text-align:center; border-bottom:2px solid #000; padding-bottom:10px; margin-bottom:14px; }
.declaration-header h1 { color:#000; font-size:13px; margin:0; }
.declaration-header h2 { color:#000; font-size:17px; margin:10px 0 0; }
.declaration-paper table { table-layout:fixed; }
.declaration-paper th, .declaration-paper td { overflow-wrap:anywhere; word-break:normal; }
.legal-block { margin-top:14px; color:#000; font-size:12px; line-height:1.45; }
.legal-block p { margin:0 0 7px; }
.legal-name { min-width:190px; display:inline-block; }
.attestation-line { color:#000; font-size:12px; margin-top:14px; }
.attestation-line span { display:inline-block; min-width:68px; border-bottom:1px solid #000; }
.signature-grid, .witness-grid { display:grid; grid-template-columns:1fr 1fr; gap:34px; margin-top:34px; }
.witness-grid { margin-top:38px; }
.signature-grid span, .witness-grid span { font-size:9px; text-transform:uppercase; }
.empty-inline { padding:22px; text-align:center; color:var(--coop-muted); }
@media (max-width: 1180px) {
  .beneficiary-body, .beneficiary-grid, .beneficiary-columns, .attachment-card { grid-template-columns:1fr; overflow:auto; }
  .member-panel { border-right:0; border-bottom:1px solid var(--coop-border); max-height:340px; }
}
@media (max-width: 760px) {
  .view-header { flex-direction:column; align-items:flex-start; gap:12px; }
  .header-actions { width:100%; flex-direction:column; align-items:stretch; }
  .search-input { width:100%; }
  .form-grid { grid-template-columns:1fr; }
  .entry-card-head, :deep(.beneficiary-row), :deep(.beneficiary-actions), :deep(.id-attachment-actions), :deep(.action-pair) { align-items:stretch; flex-direction:column; }
  .entry-card-head { display:flex; }
  .selected-chip { max-width:100%; justify-content:space-between; }
  :deep(.beneficiary-row) { display:flex; }
  .beneficiary-workspace { padding:14px; }
}
@media print {
  @page { size:A4 portrait; margin:12mm; }
  :global(html),
  :global(body),
  :global(#app) { height:auto !important; overflow:visible !important; background:#fff !important; }
  :global(body.printing-beneficiary .sidebar),
  :global(body.printing-beneficiary .mobile-topbar),
  :global(body.printing-beneficiary .mobile-overlay),
  :global(body.printing-beneficiary .toast-container) { display:none !important; }
  :global(body.printing-beneficiary .app-shell),
  :global(body.printing-beneficiary .main-area) { display:block !important; min-height:auto !important; width:100% !important; overflow:visible !important; margin:0 !important; padding:0 !important; background:#fff !important; }
  :global(body.printing-beneficiary) { background:#fff !important; }
  .no-print, .entry-card, .compliance-card, .list-card { display:none !important; }
  .beneficiary-wrap, .beneficiary-body, .beneficiary-workspace { display:block; height:auto; overflow:visible; padding:0; background:#fff; width:100%; }
  .declaration-card { display:block; padding:0; margin:0; border:0; box-shadow:none; background:#fff; width:100%; }
  .declaration-paper {
    width:100% !important;
    max-width:100% !important;
    min-height:auto !important;
    margin:0 !important;
    padding:0 !important;
    box-shadow:none !important;
    border-radius:0 !important;
    overflow:visible !important;
    font-size:10.5px;
    line-height:1.35;
  }
  .declaration-header { padding-bottom:7px; margin-bottom:9px; }
  .declaration-header h1 { font-size:12px; }
  .declaration-header h2 { font-size:15px; margin-top:6px; }
  .declaration-paper .pdf-address { font-size:9px; }
  .declaration-paper .pdf-grid-2 { gap:5px 14px; }
  .declaration-paper .pdf-field { min-width:95px; max-width:100%; }
  .declaration-paper .pdf-section-title { margin:8px 0 4px; }
  .declaration-paper table { width:100%; table-layout:fixed; margin-top:4px; page-break-inside:avoid; }
  .declaration-paper table th { padding:3px 4px; font-size:9px; }
  .declaration-paper table td { padding:2px 4px; font-size:9px; }
  .legal-block { font-size:10.5px; line-height:1.35; margin-top:9px; }
  .legal-block p { margin-bottom:5px; }
  .legal-name { min-width:145px; }
  .attestation-line { font-size:10.5px; margin-top:8px; }
  .attestation-line span { min-width:52px; }
  .signature-grid, .witness-grid { gap:22px; margin-top:24px; page-break-inside:avoid; }
  .witness-grid { margin-top:28px; }
  .declaration-paper .pdf-sig-line { height:20px; }
}
</style>
