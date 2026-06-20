import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/attendance_provider.dart';
import '../core/constants/app_colors.dart';
import '../models/attendance_session_model.dart';

class AttendanceListScreen extends StatelessWidget {
  const AttendanceListScreen({super.key});

  Color _getStatusColor(String status) {
    switch (status.toLowerCase()) {
      case 'hadir':
        return AppColors.success;
      case 'alpha':
        return AppColors.error;
      case 'izin':
        return AppColors.info;
      case 'sakit':
        return AppColors.warning;
      default:
        return AppColors.textSecondary;
    }
  }

  void _showUpdateStatusDialog(BuildContext context, AttendanceRecordModel record) {
    showDialog(
      context: context,
      builder: (context) {
        String selectedStatus = record.status;
        return StatefulBuilder(
          builder: (context, setState) {
            return AlertDialog(
              title: Text('Ubah Status: ${record.studentName}'),
              content: Column(
                mainAxisSize: MainAxisSize.min,
                children: ['hadir', 'alpha', 'izin', 'sakit', 'belum_scan', 'terlambat']
                    .map((status) => RadioListTile<String>(
                          title: Text(status.toUpperCase()),
                          value: status,
                          groupValue: selectedStatus,
                          onChanged: (value) {
                            setState(() {
                              selectedStatus = value!;
                            });
                          },
                        ))
                    .toList(),
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.of(context).pop(),
                  child: const Text('Batal'),
                ),
                ElevatedButton(
                  onPressed: () async {
                    Navigator.of(context).pop();
                    
                    showDialog(
                      context: context,
                      barrierDismissible: false,
                      builder: (c) => const Center(child: CircularProgressIndicator()),
                    );

                    final success = await Provider.of<AttendanceProvider>(context, listen: false)
                        .updateStatus(record.id, selectedStatus);

                    if (!context.mounted) return;
                    Navigator.of(context).pop(); // hide loading

                    if (success) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Status berhasil diubah'), backgroundColor: AppColors.success),
                      );
                    } else {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Gagal mengubah status'), backgroundColor: AppColors.error),
                      );
                    }
                  },
                  child: const Text('Simpan'),
                )
              ],
            );
          },
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Daftar Hadir'),
        backgroundColor: AppColors.primary,
        foregroundColor: Colors.white,
      ),
      body: Consumer<AttendanceProvider>(
        builder: (context, provider, child) {
          if (provider.isLoading) {
            return const Center(child: CircularProgressIndicator());
          }

          if (provider.attendanceRecords.isEmpty) {
            return const Center(child: Text('Belum ada data siswa.'));
          }

          return ListView.builder(
            itemCount: provider.attendanceRecords.length,
            padding: const EdgeInsets.all(16),
            itemBuilder: (context, index) {
              final record = provider.attendanceRecords[index];
              return Card(
                margin: const EdgeInsets.only(bottom: 12),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                child: ListTile(
                  title: Text(
                    record.studentName,
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                  subtitle: Text('NIS: ${record.nis}'),
                  trailing: InkWell(
                    onTap: () => _showUpdateStatusDialog(context, record),
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                      decoration: BoxDecoration(
                        color: _getStatusColor(record.status).withAlpha(51),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: _getStatusColor(record.status)),
                      ),
                      child: Text(
                        record.status.toUpperCase(),
                        style: TextStyle(
                          color: _getStatusColor(record.status),
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }
}
