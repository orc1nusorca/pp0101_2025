let currentSolvedTasks = [];
let allAchievements = [];
let userAchievements = [];
let taskStartTime = null;
let attemptCount = 0;

function showMessage(msg, isError = false) {
  const messages = document.getElementById('messages');
  messages.textContent = msg;
  messages.className = isError ? 'error' : 'success';
  setTimeout(() => { messages.textContent = ''; }, 5000);
}

const API_BASE = 'http://localhost/programm_simulator_api'; 

async function apiPost(endpoint, data) {
  try {
    const response = await fetch(`${API_BASE}/${endpoint}`, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(data),
      credentials: 'include'
    });

    const text = await response.text();
    
    try {
      return JSON.parse(text);
    } catch {
      throw new Error(`Invalid JSON: ${text.slice(0, 100)}`);
    }
    
  } catch (e) {
    return {
      error: e.name === 'TypeError' ? 'Network Error' : e.message,
      responseText: e.response?.text()
    };
  }
}

async function apiGet(endpoint) {
  try {
    const res = await fetch(API_BASE + '/' + endpoint, {
      method: 'GET',
      credentials: 'include',
    });

    const text = await res.text();
    try {
      return JSON.parse(text);
    } catch (e) {
      console.error('Invalid JSON response:', text);
      return { error: `Server error: ${text.substring(0, 100)}` };
    }
  } catch (e) {
    console.error('Network error:', e);
    return { error: 'Network connection failed' };
  }
}

let currentUser = null;
let currentTask = null;

// Elements shorthand
const authSection = document.getElementById('auth-section');
const playerDashboard = document.getElementById('player-dashboard');
const adminDashboard = document.getElementById('admin-dashboard');
const loginForm = document.getElementById('login-form');
const registerForm = document.getElementById('register-form');
const playerNameSpan = document.getElementById('player-name');
const playerLevelSpan = document.getElementById('player-level');
const playerCoinsSpan = document.getElementById('player-coins');
const playerTaskDifficulty = document.getElementById('player-task-difficulty');
const btnGetTask = document.getElementById('btn-get-task');
const taskArea = document.getElementById('task-area');
const taskDesc = document.getElementById('task-desc');
const taskSolutionInput = document.getElementById('task-solution-input-answer');
const btnSubmitSolution = document.getElementById('btn-submit-solution');
const btnCancelTask = document.getElementById('btn-cancel-task');
const playerLogoutBtn = document.getElementById('player-logout');
const adminLogoutBtn = document.getElementById('admin-logout');
const adminNameSpan = document.getElementById('admin-name');
const adminUsersList = document.getElementById('admin-users-list');
const adminTasksList = document.getElementById('admin-tasks-list');
const btnAddTask = document.getElementById('btn-add-task');
const adminTaskForm = document.getElementById('admin-task-form');
const taskIdInput = document.getElementById('task-id');
const taskDescInput = document.getElementById('task-desc-input');
const taskSolInput = document.getElementById('task-solution-input');
const taskDifficultyInput = document.getElementById('task-difficulty-input');
const btnSaveTask = document.getElementById('btn-save-task');
const btnCancelTaskForm = document.getElementById('btn-cancel-task-form');

function showSection(role) {
  authSection.classList.add('hidden');
  playerDashboard.classList.add('hidden');
  adminDashboard.classList.add('hidden');
  taskArea.classList.add('hidden');
  adminTaskForm.classList.add('hidden');
  if (!role) {
    authSection.classList.remove('hidden');
  } else if (role === 'player') {
    playerDashboard.classList.remove('hidden');
  } else if (role === 'admin') {
    adminDashboard.classList.remove('hidden');
  }
}

// Login
loginForm.onsubmit = async (e) => {
    e.preventDefault();
    const username = document.getElementById('login-username').value.trim();
    const password = document.getElementById('login-password').value;
    
    if (!username || !password) {
        showMessage('Пожалуйста заполните все поля', true);
        return;
    }
    
    const result = await apiPost('login.php', {username, password});
    if (result.error) {
        showMessage(result.error, true);
        return;
    }
    
    currentUser = result.user;
    
    // Загружаем и применяем настройки ПЕРЕД показом интерфейса
    await loadAndApplySettings();
    
    if (currentUser.role === 'admin') {
        showSection('admin');
        await loadAdminData();
    } else {
        showSection('player');
        updatePlayerDashboard();
    }
    
    showMessage('Вход успешен');
};

// Register
registerForm.onsubmit = async (e) => {
  e.preventDefault();
  const username = document.getElementById('register-username').value.trim();
  const password = document.getElementById('register-password').value;
  const role = document.getElementById('register-role').value;
  if (!username || !password) {
    showMessage('Пожалуйста заполните все поля', true);
    return;
  }
  const result = await apiPost('register.php', {username, password, role});
  if (result.error) {
    showMessage(result.error, true);
    return;
  }
  showMessage('Регистрация успешна, теперь войдите!');
  registerForm.reset();
};

// Logout
playerLogoutBtn.onclick = async () => {
  await apiGet('logout.php');
  currentUser = null;
  showSection(null);
};

adminLogoutBtn.onclick = async () => {
  await apiGet('logout.php');
  currentUser = null;
  showSection(null);
};

// Player dashboard
function updatePlayerDashboard() {
  playerNameSpan.textContent = currentUser.username;
  playerLevelSpan.textContent = currentUser.level;
  playerCoinsSpan.textContent = currentUser.coins;
  document.getElementById('solved-count').textContent = currentSolvedTasks.length;
  taskArea.classList.add('hidden');
  taskSolutionInput.value = '';
  // Обновляем прогресс уровня
    updateLevelProgress();
    
    // Обновляем значок достижений
    updateAchievementsBadge();
}

// Get task
btnGetTask.onclick = async () => {
  taskStartTime = Date.now(); // Засекаем время начала
  attemptCount = 0;
  const diff = playerTaskDifficulty.value;
  const tasks = await apiGet(`tasks.php?difficulty=${diff}`);
  
  if (tasks.error) {
    showMessage(tasks.error, true);
    return;
  }
  
  if (tasks.length === 0) {
    showMessage('Нет доступных задач для этого уровня', true);
    return;
  }

  // Фильтруем задачи, которые уже решены (на случай кеширования)
  const unsolvedTasks = tasks.filter(t => 
    !currentSolvedTasks.some(st => st.task_id === t.id)
  );

  if (unsolvedTasks.length === 0) {
    showMessage('Все задачи этого уровня уже решены!', true);
    return;
  }

  currentTask = unsolvedTasks[Math.floor(Math.random() * unsolvedTasks.length)];
  taskDesc.textContent = currentTask.description;
  taskArea.classList.remove('hidden');
};

// Cancel task
btnCancelTask.onclick = () => {
  taskArea.classList.add('hidden');
  currentTask = null;
  taskSolutionInput.value = '';
  showMessage('Задача отменена.');
};

// Submit solution
btnSubmitSolution.onclick = async () => {
  if (!currentTask) return;

  const solution = taskSolutionInput.value.trim();
  if (!solution) {
    showMessage('Введите решение', true);
    return;
  }

  attemptCount++; // Увеличиваем счетчик попыток
  const solutionTime = Math.floor((Date.now() - taskStartTime) / 1000); // Время в секундах

  const res = await apiPost('solve_task.php', {
    task_id: currentTask.id,
    solution: taskSolutionInput.value,
    category: currentTask.category, // Предполагаем, что задача содержит категорию
    attempts: attemptCount,
    solution_time: solutionTime
  });

  if (res.error) {
    showMessage(res.error, true);
    // Если задача уже решена, обновляем список
    if (res.error.includes('already solved')) {
      await updateSolvedTasks();
    }
    return;
  }

  // Обновляем данные пользователя и список решенных задач
  currentUser.coins = res.coins;
  currentUser.xp = res.xp;
  currentUser.level = res.level;
  await updateSolvedTasks();
  updatePlayerDashboard();
  
  // Показываем сообщение о достижениях, если они были получены
  if (res.achievements_unlocked && res.achievements_unlocked.length > 0) {
    const achievementsText = res.achievements_unlocked.join(', ');
    showMessage(`Задача решена! Получено достижение: ${achievementsText}`);
  } else {
    showMessage(`Задача решена! Получено: ${res.coins - currentUser.coins + res.coins} монет, ${res.xp - currentUser.xp + res.xp} опыта`);
  }
  
  taskArea.classList.add('hidden');
};

// Новая функция для загрузки решенных задач
async function updateSolvedTasks() {
  const res = await apiGet('solved_tasks.php');
  if (!res.error && res.solved_tasks) {
    currentSolvedTasks = res.solved_tasks;
    document.getElementById('solved-count').textContent = currentSolvedTasks.length;
  }
}

// Обновленная функция
async function showSolvedTasks() {
  try {
    await updateSolvedTasks();
    
    const modal = document.getElementById('solved-tasks-modal');
    const list = document.getElementById('solved-tasks-list');
    
    if (currentSolvedTasks.length === 0) {
      list.innerHTML = '<p>Вы еще не решили ни одной задачи</p>';
    } else {
      list.innerHTML = currentSolvedTasks.map((t, i) => `
        <div class="solved-task">
          <p>${i+1}. ${t.description?.substring(0, 50)}...</p>
          <small>Решено: ${new Date(t.solved_at).toLocaleDateString()}</small>
        </div>
      `).join('');
    }
    
    modal.classList.remove('hidden');
    document.body.classList.add('modal-open');
  } catch (error) {
    console.error('Error showing solved tasks:', error);
    showMessage('Ошибка загрузки истории задач', true);
  }
}

// Обновленный обработчик закрытия
function closeSolvedTasksModal() {
  document.getElementById('solved-tasks-modal').classList.add('hidden');
  document.body.classList.remove('modal-open');
}

// Обновленная инициализация
document.addEventListener('DOMContentLoaded', function() {
  // Привязка кнопки показа истории
  document.querySelector('#player-stats button').addEventListener('click', showSolvedTasks);
  
  // Привязка кнопки закрытия
  document.querySelector('#solved-tasks-modal .close').addEventListener('click', closeSolvedTasksModal);
  
  // Клик вне модалки
  document.getElementById('solved-tasks-modal').addEventListener('click', function(event) {
    if (event.target === this) {
      closeSolvedTasksModal();
    }
  });
});

async function showAllAchievements() {
  try {
    const [allRes, userRes] = await Promise.all([
      apiGet('achievements.php'),
      apiGet('achievements.php?action=user')
    ]);

    // Сохраняем достижения пользователя
      userAchievements = userRes || [];
      
      // Обновляем значок
      updateAchievementsBadge();

    if (allRes.error || userRes.error) {
      showMessage(`Ошибка загрузки: ${allRes.error || userRes.error}`, true);
      return;
    }

    allAchievements = allRes;
    userAchievements = userRes;
    renderAchievementsList();
    document.getElementById('achievements-modal').classList.remove('hidden');
    document.body.classList.add('modal-open');
    
  } catch (e) {
    showMessage('Неизвестная ошибка при загрузке', true);
    console.error('Achievements error:', e);
  }
}

function renderAchievementsList() {
  const container = document.getElementById('all-achievements-list');
  container.innerHTML = '';

  allAchievements.forEach(ach => {
    const isUnlocked = userAchievements.some(uAch => uAch.id === ach.id);
    const progress = calculateAchievementProgress(ach);

    const item = document.createElement('div');
    item.className = `achievement-item ${isUnlocked ? 'unlocked' : ''}`;
    item.innerHTML = `
      <div class="achievement-header">
        <h4>${ach.name} ${isUnlocked ? '✓' : ''}</h4>
        ${ach.reward_coins ? `<span class="coins">+${ach.reward_coins} монет</span>` : ''}
      </div>
      <p>${ach.description}</p>
      ${!isUnlocked && progress ? 
        `<div class="achievement-progress">Прогресс: ${progress}</div>` : ''}
    `;
    
    container.appendChild(item);
  });
}

function calculateAchievementProgress(achievement) {
  // Получаем необходимые данные из решенных задач
  const solvedTasks = currentSolvedTasks || [];
  
  // Для каждого типа достижения своя логика расчета
  switch(achievement.name) {
    case 'Ночная сова': {
      const nightSolved = solvedTasks.filter(t => {
        if(!t.solved_at) return false;
        const hours = new Date(t.solved_at).getHours();
        return hours >= 0 && hours < 5;
      }).length;
      return nightSolved > 0 ? 'Получено!' : `${nightSolved}/1`;
    }

    case 'Эрудит': {
      const categories = [...new Set(solvedTasks
        .filter(t => t.category)
        .map(t => t.category))];
      return `${categories.length}/${achievement.required_count}`;
    }

    case 'Скорострел': {
      const fastSolutions = solvedTasks.filter(t => 
        t.solution_time && t.solution_time < 300
      ).length;
      return `${fastSolutions}/${achievement.required_count}`;
    }

    case 'Мастер сложных задач': {
      const hardSolved = solvedTasks.filter(t => 
        t.difficulty === 'hard'
      ).length;
      return `${hardSolved}/${achievement.required_count}`;
    }

    case 'Легенда кода':
    case 'Стратег':
    case 'Новичок': {
      const totalSolved = solvedTasks.length;
      return `${totalSolved}/${achievement.required_count}`;
    }

    case 'Перфекционист': {
      const firstTrySolved = solvedTasks.filter(t => 
        t.attempts && t.attempts === 1
      ).length;
      return `${firstTrySolved}/${achievement.required_count}`;
    }

    default: {
      // Общий случай для достижений с нестандартной логикой
      if(achievement.required_count) {
        return `0/${achievement.required_count}`;
      }
      return 'Недоступно';
    }
  }
}

function closeAchievementsModal() {
  document.getElementById('achievements-modal').classList.add('hidden');
  document.body.classList.remove('modal-open');
}

// Закрытие по клику вне модалки
window.addEventListener('click', (e) => {
  const achievementsModal = document.getElementById('achievements-modal');
  if (e.target === achievementsModal) {
    closeAchievementsModal();
  }
  
  const solvedTasksModal = document.getElementById('solved-tasks-modal');
  if (e.target === solvedTasksModal) {
    closeSolvedTasksModal();
  }
});

// Admin Management
async function loadAdminData() {
  adminNameSpan.textContent = currentUser.username;
  await loadAdminUsers();
  await loadAdminTasks();
}

async function loadAdminUsers() {
  const res = await apiGet('admin/users.php?action=list');
  if (res.error) {
    showMessage(res.error, true);
    return;
  }
  adminUsersList.innerHTML = '';
  res.forEach(u => {
    const div = document.createElement('div');
    div.textContent = `${u.username} [${u.role}] ${u.blocked?'[Заблокирован]':''} - Монеты: ${u.coins} Уровень: ${u.level}`;
    const btnBlock = document.createElement('button');
    btnBlock.textContent = u.blocked ? 'Разблокировать' : 'Заблокировать';
    btnBlock.onclick = () => toggleBlockUser(u.id, u.blocked);
    const btnDelete = document.createElement('button');
    btnDelete.textContent = 'Удалить';
    btnDelete.onclick = () => deleteUser(u.id);
    div.appendChild(btnBlock);
    div.appendChild(btnDelete);
    adminUsersList.appendChild(div);
  });
}

async function toggleBlockUser(userId, currentlyBlocked) {
  let action = currentlyBlocked ? 'unblock' : 'block';
  const res = await apiPost(`admin/users.php?action=${action}`, {id: userId});
  if (res.error) {
    showMessage(res.error, true);
    return;
  }
  showMessage(res.success || 'Успешно');
  loadAdminUsers();
}

async function deleteUser(userId) {
  if (!confirm('Вы действительно хотите удалить пользователя?')) return;
  const res = await apiPost('admin/users.php?action=delete', {id: userId});
  if (res.error) {
    showMessage(res.error, true);
    return;
  }
  showMessage(res.success || 'Удалено');
  loadAdminUsers();
}

async function loadAdminTasks() {
  const res = await apiGet('admin/tasks.php?action=list');
  if (res.error) {
    showMessage(res.error, true);
    return;
  }
  adminTasksList.innerHTML = '';
  res.forEach(t => {
    const div = document.createElement('div');
    div.textContent = `[${t.difficulty}] ${t.description.substring(0, 50)}`;
    const btnEdit = document.createElement('button');
    btnEdit.textContent = 'Редактировать';
    btnEdit.onclick = () => showTaskForm(t);
    const btnDelete = document.createElement('button');
    btnDelete.textContent = 'Удалить';
    btnDelete.onclick = () => deleteTask(t.id);
    div.appendChild(btnEdit);
    div.appendChild(btnDelete);
    adminTasksList.appendChild(div);
  });
}

function showTaskForm(task=null) {
  adminTaskForm.classList.remove('hidden');
  if (!task) {
    taskIdInput.value = '';
    taskDescInput.value = '';
    taskSolInput.value = '';
    taskDifficultyInput.value = 'easy';
  } else {
    taskIdInput.value = task.id;
    taskDescInput.value = task.description;
    taskSolInput.value = task.solution;
    taskDifficultyInput.value = task.difficulty;
  }
}

btnAddTask.onclick = () => {
  showTaskForm();
};

btnCancelTaskForm.onclick = () => {
  adminTaskForm.classList.add('hidden');
};

btnSaveTask.onclick = async () => {
  const id = taskIdInput.value.trim();
  const desc = taskDescInput.value.trim();
  const sol = taskSolInput.value.trim();
  const diff = taskDifficultyInput.value;

  if (!desc || !sol) {
    showMessage('Заполните все поля задачи', true);
    return;
  }

  const payload = {description: desc, solution: sol, difficulty: diff};
  let url = 'admin/tasks.php?action=add';

  if (id) {
    payload.id = id;
    url = 'admin/tasks.php?action=edit';
  }
  const res = await apiPost(url, payload);
  if (res.error) {
    showMessage(res.error, true);
    return;
  }
  showMessage(res.success || 'Успешно');
  adminTaskForm.classList.add('hidden');
  loadAdminTasks();
};

async function deleteTask(id) {
  if (!confirm('Удалить задачу?')) return;
  const res = await apiPost('admin/tasks.php?action=delete', {id});
  if (res.error) {
    showMessage(res.error, true);
    return;
  }
  showMessage(res.success || 'Удалено');
  loadAdminTasks();
}

// On load, check if logged in by calling user_info.php
(async () => {
  const res = await apiGet('user_info.php');
  if (res.error) {
    showSection(null);
  } else {
    currentUser = res;
    await updateSolvedTasks(); // Загружаем решенные задачи
    if (res.role === 'admin') {
      showSection('admin');
      await loadAdminData();
    } else {
      showSection('player');
      updatePlayerDashboard();
    }
  }
})();

let activeUpgrades = {};
let upgradeTimer = null;

async function showShop() {
  try {
    // Остановить предыдущий таймер
    if (upgradeTimer) clearInterval(upgradeTimer);
    
    const [shopRes, userRes] = await Promise.all([
      apiGet('shop.php?action=list'),
      apiGet('shop.php?action=active')
    ]);
    
    if (shopRes.error || userRes.error) {
      showMessage(shopRes.error || userRes.error, true);
      return;
    }
    
    document.getElementById('coins-count').textContent = currentUser.coins;
    
    // Обрабатываем активные улучшения
    activeUpgrades = {};
    const now = Date.now();
    
    userRes.forEach(upgrade => {
      // Рассчитываем время истечения
      const expires_at = upgrade.duration > 0 
        ? (upgrade.purchased_at * 1000) + (upgrade.duration * 60000)
        : null;
      
      // Добавляем только активные улучшения
      if (!expires_at || expires_at > now) {
        activeUpgrades[upgrade.user_id] = {
          ...upgrade,
          purchased_at: upgrade.purchased_at * 1000,
          expires_at
        };
      }
    });
    
    // Отображаем доступные улучшения
    renderShopItems(shopRes);
    
    // Отображаем активные улучшения
    renderActiveUpgrades();
    
    document.getElementById('shop-modal').classList.remove('hidden');
    document.body.classList.add('modal-open');
    
    // Запускаем таймер для обновления времени
    startUpgradeTimer();
    
  } catch (e) {
    showMessage('Ошибка загрузки магазина', true);
    console.error(e);
  }
}

function renderShopItems(items) {
  const shopContainer = document.getElementById('shop-items');
  shopContainer.innerHTML = '';
  
  items.forEach(item => {
    const itemEl = document.createElement('div');
    itemEl.className = 'shop-item';
    itemEl.innerHTML = `
      <h4>${item.name} - ${item.price} монет</h4>
      <p>${item.description}</p>
      <button onclick="purchaseUpgrade(${item.id})">Купить</button>
    `;
    shopContainer.appendChild(itemEl);
  });
}

function renderActiveUpgrades() {
  const activeContainer = document.getElementById('active-upgrades');
  activeContainer.innerHTML = '';
  
  const now = Date.now();
  
  // Фильтруем активные улучшения
  const active = Object.values(activeUpgrades).filter(upgrade => {
    return !upgrade.expires_at || upgrade.expires_at > now;
  });
  
  if (active.length === 0) {
    activeContainer.innerHTML = '<p>У вас нет активных улучшений</p>';
    return;
  }
  
  active.forEach(upgrade => {
    const upgradeEl = document.createElement('div');
    upgradeEl.className = 'active-upgrade';
    
    let timeInfo = '';
    let cancelButton = '';
    
    if (upgrade.expires_at) {
      const timeLeft = Math.max(0, Math.floor((upgrade.expires_at - now) / 60000));
      timeInfo = `Осталось: ${timeLeft} мин`;
      
      // Показываем кнопку "Отменить" только если время еще не истекло
      if (timeLeft > 0) {
        cancelButton = `<button onclick="removeUpgrade(${upgrade.user_id})">Отменить</button>`;
      }
    } else {
      // Для постоянных улучшений всегда показываем кнопку отмены
      cancelButton = `<button onclick="removeUpgrade(${upgrade.user_id})">Отменить</button>`;
    }
    
    upgradeEl.innerHTML = `
      <h4>${upgrade.name}</h4>
      <p>${upgrade.description}</p>
      <small>Куплено: ${new Date(upgrade.purchased_at).toLocaleTimeString()}</small>
      ${timeInfo ? `<small>${timeInfo}</small>` : ''}
      ${cancelButton}
    `;
    activeContainer.appendChild(upgradeEl);
  });
}

function startUpgradeTimer() {
  // Обновляем каждую секунду для плавного изменения времени
  upgradeTimer = setInterval(() => {
    const now = Date.now();
    let hasActive = false;
    
    // Проверяем, есть ли активные улучшения с таймером
    for (const id in activeUpgrades) {
      const upgrade = activeUpgrades[id];
      
      // Если время истекло, удаляем улучшение
      if (upgrade.expires_at && upgrade.expires_at <= now) {
        delete activeUpgrades[id];
        showMessage(`Улучшение "${upgrade.name}" закончилось`);
      } else {
        hasActive = true;
      }
    }
    
    // Если есть активные улучшения, обновляем интерфейс
    if (hasActive) {
      renderActiveUpgrades();
    } else {
      // Если активных улучшений нет, очищаем контейнер
      document.getElementById('active-upgrades').innerHTML = '<p>У вас нет активных улучшений</p>';
    }
    
  }, 1000);
}

async function purchaseUpgrade(upgradeId) {
  try {
    const res = await apiPost('shop.php?action=purchase', {id: upgradeId});
    if (res.error) {
      showMessage(res.error, true);
      return;
    }
    
    // Обновляем баланс
    currentUser.coins = res.new_balance;
    document.getElementById('coins-count').textContent = res.new_balance;
    document.getElementById('player-coins').textContent = res.new_balance;
    
    // Добавляем улучшение в активные
    const upgrade = res.upgrade;
    activeUpgrades[upgrade.user_id] = {
      ...upgrade,
      purchased_at: upgrade.purchased_at * 1000,
      expires_at: upgrade.duration > 0 
        ? (upgrade.purchased_at * 1000) + (upgrade.duration * 60000)
        : null
    };
    
    renderActiveUpgrades();
    showMessage('Улучшение приобретено!');
    
  } catch (e) {
    showMessage('Ошибка покупки', true);
    console.error(e);
  }
}

async function removeUpgrade(upgradeId) {
  try {
    // Получаем название улучшения для сообщения
    const upgradeName = activeUpgrades[upgradeId]?.name || 'улучшение';
    
    // Подтверждение действия
    const isConfirmed = confirm(`Вы уверены, что хотите отменить ${upgradeName}?`);
    if (!isConfirmed) return;
    
    const res = await apiPost('shop.php?action=remove', {id: upgradeId});
    if (res.error) {
      showMessage(res.error, true);
      return;
    }
    
    // Удаляем улучшение из активных
    delete activeUpgrades[upgradeId];
    renderActiveUpgrades();
    showMessage('Улучшение отменено');
    
  } catch (e) {
    showMessage('Ошибка отмены улучшения', true);
    console.error(e);
  }
}

function closeShopModal() {
  document.getElementById('shop-modal').classList.add('hidden');
  document.body.classList.remove('modal-open');
  
  // Останавливаем таймер при закрытии магазина
  if (upgradeTimer) {
    clearInterval(upgradeTimer);
    upgradeTimer = null;
  }
}

// Инициализация
document.getElementById('btn-show-shop').addEventListener('click', showShop);

// ===== ИСПРАВЛЕННЫЙ КОД ДЛЯ КАСТОМИЗАЦИИ =====

// Константы
const UPLOADS_BASE = `${window.location.origin}/uploads`;

// SVG по умолчанию
const defaultAvatarSVG = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4" fill="%236ad4ff"/><path fill="%236ad4ff" d="M20 19v1a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6z"/></svg>';

// Настройки пользователя
let userSettings = {
    theme: 'default',
    avatar: null,
    showLevelProgress: true,
    showAchievementsBadge: true
};

// Глобальные ссылки на элементы
let customizationModal = null;
let avatarPreview = null;
let playerAvatar = null;

// Инициализация обработчиков событий
function initCustomization() {
    // Находим основные элементы
    customizationModal = document.getElementById('customization-modal');
    avatarPreview = document.getElementById('avatar-preview');
    playerAvatar = document.getElementById('player-avatar');
    
    // Находим кнопки
    const btnCustomizeProfile = document.getElementById('btn-customize-profile');
    const closeCustomizationBtn = customizationModal.querySelector('.close');
    const themeOptions = document.querySelectorAll('.theme-option');
    const btnUploadAvatar = document.getElementById('btn-upload-avatar');
    const btnRemoveAvatar = document.getElementById('btn-remove-avatar');
    const btnSaveSettings = document.getElementById('btn-save-settings');

    // Устанавливаем обработчики
    if (btnCustomizeProfile) {
        btnCustomizeProfile.addEventListener('click', openCustomizationModal);
    }

    if (closeCustomizationBtn) {
        closeCustomizationBtn.addEventListener('click', closeCustomizationModal);
    }
    
    if (themeOptions.length > 0) {
        themeOptions.forEach(option => {
            option.addEventListener('click', () => {
                selectTheme(option.dataset.theme);
            });
        });
    }
    
    if (btnUploadAvatar) {
        btnUploadAvatar.addEventListener('click', handleAvatarUpload);
    }
    
    if (btnRemoveAvatar) {
        btnRemoveAvatar.addEventListener('click', removeAvatar);
    }
    
    if (btnSaveSettings) {
        btnSaveSettings.addEventListener('click', saveCustomizationSettings);
    }
}

// Открытие модального окна настроек
async function openCustomizationModal() {
    if (!customizationModal) {
        customizationModal = document.getElementById('customization-modal');
    }
    
    // Загружаем актуальные настройки с сервера
    try {
        await loadUserSettings();
        updateThemeSelection();
        updateAvatarPreview();
        
        if (customizationModal) {
            customizationModal.classList.remove('hidden');
            document.body.classList.add('modal-open');
        }
    } catch (error) {
        console.error('Error opening customization modal:', error);
        showMessage('Ошибка загрузки настроек', true);
    }
}

// Закрытие модального окна
function closeCustomizationModal() {
    if (customizationModal) {
        customizationModal.classList.add('hidden');
        document.body.classList.remove('modal-open');
    }
}

// Клик вне модалки
document.addEventListener('click', (e) => {
    if (customizationModal && e.target === customizationModal) {
        closeCustomizationModal();
    }
});

// Выбор темы
function selectTheme(theme) {
    userSettings.theme = theme;
    updateThemeSelection();
    applyTheme(theme);
}

// Обновление выбора темы в UI
function updateThemeSelection() {
    const themeOptions = document.querySelectorAll('.theme-option');
    if (themeOptions.length > 0) {
        themeOptions.forEach(option => {
            option.classList.remove('selected');
            if (option.dataset.theme === userSettings.theme) {
                option.classList.add('selected');
            }
        });
    }
}

// Применение темы к интерфейсу
function applyTheme(theme) {
    // Удаляем все классы тем
    document.body.classList.remove(
        'default-theme', 
        'dark-theme', 
        'blue-theme', 
        'green-theme', 
        'purple-theme'
    );
    
    // Добавляем выбранную тему
    if (theme) {
        document.body.classList.add(theme + '-theme');
    }
}

// Загрузка аватара
async function handleAvatarUpload() {
    const avatarUpload = document.getElementById('avatar-upload');
    if (!avatarUpload) return;
    
    const file = avatarUpload.files[0];
    if (!file) {
        showMessage('Выберите файл изображения', true);
        return;
    }
    
    // Показываем предпросмотр
    const reader = new FileReader();
    reader.onload = function(e) {
        if (avatarPreview) {
            avatarPreview.src = e.target.result;
        }
        if (playerAvatar) {
            playerAvatar.src = e.target.result;
        }
    };
    reader.readAsDataURL(file);
    
    // Отправка на сервер
    const formData = new FormData();
    formData.append('avatar', file);
    
    try {
        const response = await fetch(`${API_BASE}/upload_avatar.php`, {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.error) {
            showMessage(data.error, true);
            return;
        }
        
        // Сохраняем полный URL аватара
        userSettings.avatar = data.avatar_url;
        showMessage('Аватар успешно загружен!');
        
        // Обновляем аватар в UI
        if (playerAvatar) {
            playerAvatar.src = data.avatar_url;
        }
        
    } catch (error) {
        showMessage('Ошибка загрузки аватара', true);
        console.error('Avatar upload error:', error);
    }
}

// Обновление превью аватара
function updateAvatarPreview() {
    if (avatarPreview && userSettings.avatar) {
        avatarPreview.src = userSettings.avatar;
    } else if (avatarPreview) {
        avatarPreview.src = defaultAvatarSVG;
    }
}

// Удаление аватара
async function removeAvatar() {
    try {
        const response = await apiPost('remove_avatar.php', {});
        
        if (response.error) {
            showMessage(response.error, true);
            return;
        }
        
        userSettings.avatar = null;
        updateAvatarPreview();
        
        if (playerAvatar) {
            playerAvatar.src = defaultAvatarSVG;
        }
        
        showMessage('Аватар удалён');
        
    } catch (error) {
        showMessage('Ошибка удаления аватара', true);
        console.error('Remove avatar error:', error);
    }
}

// Сохранение настроек
async function saveCustomizationSettings() {
    const showLevelProgress = document.getElementById('show-level-progress');
    const showAchievementsBadge = document.getElementById('show-achievements-badge');
    
    if (showLevelProgress && showAchievementsBadge) {
        userSettings.showLevelProgress = showLevelProgress.checked;
        userSettings.showAchievementsBadge = showAchievementsBadge.checked;
    }
    
    try {
        // Извлекаем только имя файла для сохранения в БД
        const avatarFilename = userSettings.avatar 
            ? userSettings.avatar.split('/').pop() 
            : null;
        
        const response = await apiPost('user_settings.php', {
            theme: userSettings.theme,
            avatar: avatarFilename,
            show_level_progress: userSettings.showLevelProgress,
            show_achievements_badge: userSettings.showAchievementsBadge
        });
        
        if (response.error) {
            showMessage(response.error, true);
            return;
        }
        
        // Применяем настройки на странице
        applyUserSettings();
        
        showMessage('Настройки сохранены!');
        closeCustomizationModal();
        
        // Сохраняем состояние в localStorage
        saveAppState();
        
    } catch (error) {
        showMessage('Ошибка сохранения настроек', true);
        console.error('Save settings error:', error);
    }
}

// Загрузка настроек пользователя
async function loadUserSettings() {
    try {
        const response = await apiGet('user_settings.php');
        
        if (response.error) {
            return null;
        }
        
        // Формируем полный URL аватара
        const avatarUrl = response.avatar 
            ? `${UPLOADS_BASE}/${response.avatar}` 
            : null;
        
        return {
            theme: response.theme || 'default',
            avatar: avatarUrl,
            showLevelProgress: response.show_level_progress !== undefined ? 
                Boolean(response.show_level_progress) : true,
            showAchievementsBadge: response.show_achievements_badge !== undefined ? 
                Boolean(response.show_achievements_badge) : true
        };
        
    } catch (error) {
        console.error('Error loading settings:', error);
        return null;
    }
}

// Применение настроек
function applyUserSettings() {
    // 1. Применяем тему
    applyTheme(userSettings.theme);
    
    // 2. Устанавливаем аватар
    if (playerAvatar) {
        if (userSettings.avatar) {
            playerAvatar.src = userSettings.avatar;
            
            // Обработка ошибок загрузки изображения
            playerAvatar.onerror = () => {
                playerAvatar.src = defaultAvatarSVG;
            };
        } else {
            playerAvatar.src = defaultAvatarSVG;
        }
    }
    
    // 3. Устанавливаем чекбоксы
    const showLevelProgress = document.getElementById('show-level-progress');
    const showAchievementsBadge = document.getElementById('show-achievements-badge');
    
    if (showLevelProgress) {
        showLevelProgress.checked = userSettings.showLevelProgress;
    }
    
    if (showAchievementsBadge) {
        showAchievementsBadge.checked = userSettings.showAchievementsBadge;
    }
    const levelProgressContainer = document.getElementById('level-progress-container');
    if (levelProgressContainer) {
      levelProgressContainer.style.display = 
        userSettings.showLevelProgress ? 'block' : 'none';
    }
    
    // Применяем настройку для значка достижений
    const achievementsBadge = document.getElementById('achievements-badge');
    if (achievementsBadge) {
      achievementsBadge.style.display = 
        userSettings.showAchievementsBadge ? 'flex' : 'none';
    }
  }

// Сохранение состояния приложения в localStorage
function saveAppState() {
    const state = {
        theme: userSettings.theme,
        avatar: userSettings.avatar,
        showLevelProgress: userSettings.showLevelProgress,
        showAchievementsBadge: userSettings.showAchievementsBadge
    };
    
    localStorage.setItem('appState', JSON.stringify(state));
}

// Загрузка состояния приложения из localStorage
function loadAppState() {
    const state = localStorage.getItem('appState');
    if (state) {
        return JSON.parse(state);
    }
    return null;
}

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', async () => {
    // Инициализация обработчиков кастомизации
    initCustomization();
    
    // Проверяем авторизацию
    const res = await apiGet('user_info.php');
    if (!res.error) {
        currentUser = res;
        
        // Загружаем и применяем настройки
        await loadAndApplySettings();
        
        if (res.role === 'admin') {
            showSection('admin');
            await loadAdminData();
        } else {
            showSection('player');
            updatePlayerDashboard();
        }
        
        // Загружаем решенные задачи
        await updateSolvedTasks();
    } else {
        // Для гостей применяем тему по умолчанию
        applyTheme('default');
    }
    
    // Загружаем состояние из localStorage ПОСЛЕ серверных настроек
    const savedState = loadAppState();
    if (savedState) {
        // Объединяем с серверными настройками
        userSettings = {...userSettings, ...savedState};
        applyUserSettings();
    }
});

// Сохранение состояния перед выходом
window.addEventListener('beforeunload', () => {
    saveAppState();
});

// Обновленный выход из системы
async function logout() {
    try {
        // Сохраняем настройки перед выходом
        saveAppState();
        
        await apiGet('logout.php');
        
        // Сбрасываем состояние
        currentUser = null;
        showSection(null);
        
        // Восстанавливаем тему по умолчанию
        applyTheme('default');
        
        // Сбрасываем аватар
        if (playerAvatar) {
            playerAvatar.src = defaultAvatarSVG;
        }
        
    } catch (error) {
        console.error('Logout error:', error);
    }
}

if (playerLogoutBtn) {
    playerLogoutBtn.onclick = logout;
}

if (adminLogoutBtn) {
    adminLogoutBtn.onclick = logout;
}

async function loadAndApplySettings() {
    try {
        const settings = await loadUserSettings();
        if (settings) {
            userSettings = settings;
            applyUserSettings();
            saveAppState(); // Сохраняем состояние после применения
            return true;
        }
    } catch (error) {
        console.error('Error loading settings:', error);
    }
    return false;
}

 function updateLevelProgress() {
    const currentLevelEl = document.getElementById('current-level');
    const nextLevelXpEl = document.getElementById('next-level-xp');
    const progressFill = document.querySelector('.progress-fill');
    const progressPercent = document.getElementById('level-progress-percent');
    
    if (!currentLevelEl || !progressFill) return;
    
    // Рассчитываем прогресс (примерная логика)
    const currentLevel = parseInt(currentUser.level) || 1;
    const currentXp = parseInt(currentUser.xp) || 0;
    
    // Формула: каждый уровень требует на 100 XP больше
    const xpForNextLevel = currentLevel * 100;
    const progress = Math.min(100, Math.floor((currentXp / xpForNextLevel) * 100));
    
    currentLevelEl.textContent = currentLevel;
    nextLevelXpEl.textContent = xpForNextLevel;
    progressFill.style.width = `${progress}%`;
    progressPercent.textContent = `${progress}%`;
  }

  // Функция обновления значка достижений
  function updateAchievementsBadge() {
    const badgeCount = document.querySelector('.badge-count');
    if (!badgeCount) return;
    
    // Показываем количество полученных достижений
    const count = userAchievements.length || 0;
    badgeCount.textContent = count;
    
    // Анимация при получении новых достижений
    if (count > 0) {
      const badge = document.getElementById('achievements-badge');
      badge.classList.add('pulse');
      setTimeout(() => badge.classList.remove('pulse'), 1000);
    }
  }