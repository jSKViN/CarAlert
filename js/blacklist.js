const API_BASE = 'api/blacklist.php';
let currentPage = 1;
let pageSize = 20;
let totalCount = 0;
let currentFilter = {
    plate_number: '',
    blacklist_type: '',
    is_active: 1
};

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const bgColor = type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500';
    toast.className = `fixed bottom-4 right-4 px-6 py-3 rounded-lg shadow-lg transform transition-all duration-300 ${bgColor} text-white`;
    toast.innerHTML = `<i class="fa fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'} mr-2"></i>${message}`;
    toast.style.transform = 'translateY(0)';
    toast.style.opacity = '1';

    setTimeout(() => {
        toast.style.transform = 'translateY(20px)';
        toast.style.opacity = '0';
    }, 3000);
}

function toggleDaysInput() {
    const typeSelect = document.getElementById('blacklist-type');
    const daysContainer = document.getElementById('days-container');
    daysContainer.style.display = typeSelect.value === '1' ? 'block' : 'none';
}

function toggleEditDaysInput() {
    const typeSelect = document.getElementById('edit-type');
    const daysContainer = document.getElementById('edit-days-container');
    daysContainer.style.display = typeSelect.value === '1' ? 'block' : 'none';
}

async function fetchBlacklistPlates() {
    const params = new URLSearchParams({
        action: 'list',
        limit: pageSize,
        offset: (currentPage - 1) * pageSize,
        ...currentFilter
    });

    if (currentFilter.plate_number) params.set('plate_number', currentFilter.plate_number);
    if (currentFilter.blacklist_type) params.set('blacklist_type', currentFilter.blacklist_type);
    if (currentFilter.is_active !== '') params.set('is_active', currentFilter.is_active);

    try {
        const response = await fetch(`${API_BASE}?${params}`);
        const result = await response.json();

        if (result.success) {
            renderPlateList(result.data.items);
            totalCount = result.data.total;
            updatePagination();
        } else {
            showToast(result.message || '加载失败', 'error');
        }
    } catch (error) {
        console.error('Error fetching blacklist plates:', error);
        showToast('网络错误', 'error');
    }
}

async function fetchStats() {
    try {
        const response = await fetch(`${API_BASE}?action=stats`);
        const result = await response.json();

        if (result.success) {
            document.getElementById('stat-total').textContent = result.data.total;
            document.getElementById('stat-temporary').textContent = result.data.temporary;
            document.getElementById('stat-permanent').textContent = result.data.permanent;
        }
    } catch (error) {
        console.error('Error fetching stats:', error);
    }
}

async function fetchLogs() {
    try {
        const response = await fetch(`${API_BASE}?action=logs&limit=50`);
        const result = await response.json();

        if (result.success) {
            renderLogList(result.data);
            if (result.data.length > 0) {
                document.getElementById('stat-recent').textContent = result.data[0].plate_number;
            }
        }
    } catch (error) {
        console.error('Error fetching logs:', error);
    }
}

function renderPlateList(plates) {
    const container = document.getElementById('plate-list');

    if (!plates || plates.length === 0) {
        container.innerHTML = `
            <div class="px-6 py-8 text-center text-gray-500">
                <i class="fa fa-inbox text-4xl mb-4"></i>
                <p>暂无拉黑记录</p>
            </div>
        `;
        return;
    }

    container.innerHTML = plates.map(plate => {
        const typeLabel = plate.blacklist_type == 2 ? '永久拉黑' : '临时拉黑';
        const typeClass = plate.blacklist_type == 2 ? 'bg-purple-100 text-purple-800' : 'bg-orange-100 text-orange-800';
        const isActive = plate.is_active == 1;
        const isExpired = plate.blacklist_type == 1 && plate.end_time && new Date(plate.end_time) < new Date();
        const statusClass = isExpired ? 'bg-gray-100 text-gray-800' : isActive ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
        const statusText = isExpired ? '已过期' : isActive ? '生效中' : '已解除';
        const endTime = plate.end_time ? new Date(plate.end_time).toLocaleString('zh-CN') : '永久';

        return `
            <div class="px-6 py-4 hover:bg-gray-50">
                <div class="flex justify-between items-center">
                    <div class="flex-1">
                        <div class="flex items-center gap-3">
                            <span class="text-lg font-semibold text-gray-800">${escapeHtml(plate.plate_number)}</span>
                            <span class="px-2 py-1 ${typeClass} rounded-full text-xs">${typeLabel}</span>
                            <span class="px-2 py-1 ${statusClass} rounded-full text-xs">${statusText}</span>
                        </div>
                        <div class="text-sm text-gray-500 mt-1">
                            ${plate.reason ? `原因：${escapeHtml(plate.reason)}` : ''}
                            ${plate.reason ? '<br>' : ''}
                            操作人：${escapeHtml(plate.operator)} |
                            结束时间：${endTime} |
                            添加时间：${new Date(plate.created_at).toLocaleString('zh-CN')}
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        ${isActive && !isExpired ? `
                            <button onclick="openEditModal('${escapeHtml(plate.plate_number)}', '${escapeHtml(plate.reason || '')}', ${plate.blacklist_type}, '${plate.operator || ''}')"
                                    class="text-blue-600 hover:text-blue-800 px-3 py-1 border border-blue-600 rounded">
                                <i class="fa fa-edit mr-1"></i>编辑
                            </button>
                            <button onclick="removePlate('${escapeHtml(plate.plate_number)}')"
                                    class="text-green-600 hover:text-green-800 px-3 py-1 border border-green-600 rounded">
                                <i class="fa fa-check mr-1"></i>解除
                            </button>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function renderLogList(logs) {
    const container = document.getElementById('log-list');

    if (!logs || logs.length === 0) {
        container.innerHTML = `
            <div class="px-6 py-4 text-center text-gray-500">
                <p>暂无操作记录</p>
            </div>
        `;
        return;
    }

    container.innerHTML = logs.map(log => {
        const actionIcon = log.action_type === 'ADD' ? 'fa-ban text-red-500' : log.action_type === 'REMOVE' ? 'fa-check text-green-500' : 'fa-edit text-blue-500';
        const actionText = log.action_type === 'ADD' ? '添加拉黑' : log.action_type === 'REMOVE' ? '解除拉黑' : '更新信息';

        return `
            <div class="px-6 py-3 flex items-center hover:bg-gray-50">
                <i class="fa ${actionIcon} mr-3 w-6 text-center"></i>
                <div class="flex-1">
                    <span class="font-semibold text-gray-800">${escapeHtml(log.plate_number)}</span>
                    <span class="text-gray-600 ml-2">${actionText}</span>
                    ${log.reason ? `<span class="text-gray-500 ml-2">- ${escapeHtml(log.reason)}</span>` : ''}
                </div>
                <div class="text-sm text-gray-500">
                    ${escapeHtml(log.operator)} | ${new Date(log.created_at).toLocaleString('zh-CN')}
                </div>
            </div>
        `;
    }).join('');
}

function updatePagination() {
    const totalPages = Math.ceil(totalCount / pageSize);
    const pageInfo = document.getElementById('total-count');
    const paginationButtons = document.getElementById('pagination-buttons');

    pageInfo.textContent = totalCount;

    if (totalPages <= 1) {
        paginationButtons.innerHTML = '';
        return;
    }

    let buttons = '';

    if (currentPage > 1) {
        buttons += `<button onclick="goToPage(${currentPage - 1})" class="px-3 py-1 border rounded hover:bg-gray-100">&laquo; 上一页</button>`;
    }

    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);

    for (let i = startPage; i <= endPage; i++) {
        buttons += `<button onclick="goToPage(${i})"
                     class="px-3 py-1 border rounded ${i === currentPage ? 'bg-blue-600 text-white' : 'hover:bg-gray-100'}">${i}</button>`;
    }

    if (currentPage < totalPages) {
        buttons += `<button onclick="goToPage(${currentPage + 1})" class="px-3 py-1 border rounded hover:bg-gray-100">下一页 &raquo;</button>`;
    }

    paginationButtons.innerHTML = buttons;
}

function goToPage(page) {
    currentPage = page;
    fetchBlacklistPlates();
}

async function addPlate(formData) {
    try {
        const response = await fetch(`${API_BASE}?action=add`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(Object.fromEntries(formData))
        });

        const result = await response.json();

        if (result.success) {
            showToast('车牌已加入拉黑列表');
            fetchBlacklistPlates();
            fetchStats();
            fetchLogs();
            document.getElementById('add-form').reset();
            document.getElementById('operator').value = 'admin';
        } else {
            showToast(result.message || '添加失败', 'error');
        }
    } catch (error) {
        console.error('Error adding plate:', error);
        showToast('网络错误', 'error');
    }
}

async function removePlate(plateNumber) {
    if (!confirm(`确定要解除 ${plateNumber} 的拉黑状态吗？`)) {
        return;
    }

    try {
        const response = await fetch(`${API_BASE}?action=remove`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                plate_number: plateNumber,
                operator: 'admin'
            })
        });

        const result = await response.json();

        if (result.success) {
            showToast('拉黑已解除');
            fetchBlacklistPlates();
            fetchStats();
            fetchLogs();
        } else {
            showToast(result.message || '解除失败', 'error');
        }
    } catch (error) {
        console.error('Error removing plate:', error);
        showToast('网络错误', 'error');
    }
}

function openEditModal(plateNumber, reason, type, operator) {
    document.getElementById('edit-plate-number').value = plateNumber;
    document.getElementById('edit-reason').value = reason;
    document.getElementById('edit-type').value = type;
    document.getElementById('edit-operator').value = operator || 'admin';

    if (type == 1) {
        document.getElementById('edit-days-container').style.display = 'block';
    } else {
        document.getElementById('edit-days-container').style.display = 'none';
    }

    document.getElementById('edit-modal').classList.add('active');
}

function closeEditModal() {
    document.getElementById('edit-modal').classList.remove('active');
}

async function updatePlate(formData) {
    try {
        const response = await fetch(API_BASE, {
            method: 'PUT',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(Object.fromEntries(formData))
        });

        const result = await response.json();

        if (result.success) {
            showToast('更新成功');
            closeEditModal();
            fetchBlacklistPlates();
            fetchLogs();
        } else {
            showToast(result.message || '更新失败', 'error');
        }
    } catch (error) {
        console.error('Error updating plate:', error);
        showToast('网络错误', 'error');
    }
}

async function testNotification() {
    try {
        const response = await fetch('api/blacklist_notify.php?action=test');
        const result = await response.json();

        if (result.success) {
            showToast('测试通知已发送', 'success');
        } else {
            showToast('通知发送失败', 'error');
        }
    } catch (error) {
        console.error('Error testing notification:', error);
        showToast('网络错误', 'error');
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.addEventListener('DOMContentLoaded', function() {
    fetchBlacklistPlates();
    fetchStats();
    fetchLogs();

    document.getElementById('blacklist-type').addEventListener('change', toggleDaysInput);
    document.getElementById('edit-type').addEventListener('change', toggleEditDaysInput);

    document.getElementById('add-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        addPlate(formData);
    });

    document.getElementById('edit-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        updatePlate(formData);
    });

    document.getElementById('close-edit-modal').addEventListener('click', closeEditModal);
    document.getElementById('cancel-edit').addEventListener('click', closeEditModal);

    document.getElementById('test-notify-btn').addEventListener('click', testNotification);

    document.getElementById('search-btn').addEventListener('click', function() {
        currentFilter.plate_number = document.getElementById('search-input').value.trim();
        currentFilter.blacklist_type = document.getElementById('filter-type').value;
        currentFilter.is_active = document.getElementById('filter-status').value;
        currentPage = 1;
        fetchBlacklistPlates();
    });

    document.getElementById('search-input').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            document.getElementById('search-btn').click();
        }
    });
});
